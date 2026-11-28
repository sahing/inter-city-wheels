<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingDispatchOrderAndTableFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('role', 'admin')->firstOrFail();
    }

    private function createBookingWithTimestamp(string $ref, string $status, string $createdAt, float $amount = 2000): Booking
    {
        $booking = Booking::create([
            'booking_reference' => $ref,
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'route_summary' => 'Kolkata → Digha',
            'pickup_location' => 'Salt Lake, Kolkata',
            'drop_location' => 'New Digha',
            'customer_name' => 'Test '.$ref,
            'customer_phone' => '+91 98300 00001',
            'selected_units' => ['sedan'],
            'base_fare' => $amount,
            'toll_fare' => 0,
            'addon_fare' => 0,
            'discount_fare' => 0,
            'total_amount' => $amount,
            'status' => $status,
            'start_otp' => '1234',
        ]);

        // Set explicit timestamps for deterministic oldest->newest assertions.
        $booking->timestamps = false;
        $booking->created_at = $createdAt;
        $booking->updated_at = $createdAt;
        $booking->save();

        return $booking;
    }

    public function test_admin_bookings_default_order_puts_oldest_requested_first_and_completed_last(): void
    {
        Booking::query()->delete();

        // Insert out of queue order deliberately.
        $completedOld = $this->createBookingWithTimestamp('ICW-COMP-OLD', 'Completed', '2026-11-20 08:00:00', 5000);
        $requestedNew = $this->createBookingWithTimestamp('ICW-REQ-NEW', 'Requested', '2026-11-25 14:00:00', 2200);
        $confirmedMid = $this->createBookingWithTimestamp('ICW-CONF-MID', 'Confirmed', '2026-11-22 10:00:00', 3100);
        $requestedOld = $this->createBookingWithTimestamp('ICW-REQ-OLD', 'Requested', '2026-11-21 09:00:00', 1800);
        $startedNew = $this->createBookingWithTimestamp('ICW-STRT-NEW', 'Started', '2026-11-26 11:00:00', 4200);
        $cancelledMid = $this->createBookingWithTimestamp('ICW-CANC-MID', 'Cancelled', '2026-11-19 07:00:00', 1500);
        $completedNew = $this->createBookingWithTimestamp('ICW-COMP-NEW', 'Completed', '2026-11-27 16:00:00', 6000);

        $apiRes = $this->actingAs($this->admin)->getJson('/api/admin/bookings')->assertOk();
        $refs = array_column($apiRes->json('bookings'), 'booking_reference');

        $this->assertSame([
            $requestedOld->booking_reference,
            $requestedNew->booking_reference,
            $confirmedMid->booking_reference,
            $startedNew->booking_reference,
            $cancelledMid->booking_reference,
            $completedOld->booking_reference,
            $completedNew->booking_reference,
        ], $refs);

        // Server-rendered admin dispatch table matches the same default order.
        $html = $this->actingAs($this->admin)->get('/')->assertOk()->getContent();
        $tbodyStart = strpos($html, 'id="admin-bookings-table-tbody"');
        $this->assertNotFalse($tbodyStart);
        $tbodyEnd = strpos($html, '</tbody>', $tbodyStart);
        $tbodyHtml = substr($html, $tbodyStart, $tbodyEnd - $tbodyStart);

        $posReqOld = strpos($tbodyHtml, 'ICW-REQ-OLD');
        $posReqNew = strpos($tbodyHtml, 'ICW-REQ-NEW');
        $posConfMid = strpos($tbodyHtml, 'ICW-CONF-MID');
        $posCompOld = strpos($tbodyHtml, 'ICW-COMP-OLD');

        $this->assertNotFalse($posReqOld);
        $this->assertLessThan($posReqNew, $posReqOld);
        $this->assertLessThan($posConfMid, $posReqNew);
        $this->assertLessThan($posCompOld, $posConfMid);
    }

    public function test_admin_bookings_status_filters_and_column_sorting_queries_work(): void
    {
        Booking::query()->delete();

        $this->createBookingWithTimestamp('ICW-REQ-1', 'Requested', '2026-11-21 09:00:00', 1500);
        $this->createBookingWithTimestamp('ICW-CONF-1', 'Confirmed', '2026-11-23 09:00:00', 4500);
        $this->createBookingWithTimestamp('ICW-COMP-1', 'Completed', '2026-11-25 09:00:00', 3000);
        $this->createBookingWithTimestamp('ICW-CANC-1', 'Cancelled', '2026-11-26 09:00:00', 900);

        // Status = Completed filter returns only completed
        $comp = $this->actingAs($this->admin)->getJson('/api/admin/bookings?status=Completed')->assertOk();
        $this->assertSame(['ICW-COMP-1'], array_column($comp->json('bookings'), 'booking_reference'));

        // Status = Requested filter returns only requested
        $req = $this->actingAs($this->admin)->getJson('/api/admin/bookings?status=Requested')->assertOk();
        $this->assertSame(['ICW-REQ-1'], array_column($req->json('bookings'), 'booking_reference'));

        // Status = active excludes Completed and Cancelled
        $active = $this->actingAs($this->admin)->getJson('/api/admin/bookings?status=active')->assertOk();
        $this->assertSame(['ICW-REQ-1', 'ICW-CONF-1'], array_column($active->json('bookings'), 'booking_reference'));

        // Fare ascending / descending sort options
        $fareAsc = $this->actingAs($this->admin)->getJson('/api/admin/bookings?sort_by=amount_asc')->assertOk();
        $this->assertSame(['ICW-CANC-1', 'ICW-REQ-1', 'ICW-COMP-1', 'ICW-CONF-1'], array_column($fareAsc->json('bookings'), 'booking_reference'));

        $fareDesc = $this->actingAs($this->admin)->getJson('/api/admin/bookings?sort_by=amount_desc')->assertOk();
        $this->assertSame(['ICW-CONF-1', 'ICW-COMP-1', 'ICW-REQ-1', 'ICW-CANC-1'], array_column($fareDesc->json('bookings'), 'booking_reference'));

        // Date newest first
        $newest = $this->actingAs($this->admin)->getJson('/api/admin/bookings?sort_by=date_desc')->assertOk();
        $this->assertSame(['ICW-CANC-1', 'ICW-COMP-1', 'ICW-CONF-1', 'ICW-REQ-1'], array_column($newest->json('bookings'), 'booking_reference'));
    }

    public function test_admin_panel_renders_dispatch_filter_options_sort_dropdown_and_universal_table_tools(): void
    {
        $html = $this->actingAs($this->admin)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="admin-booking-filter-status"', $html);
        $this->assertStringContainsString('<option value="Requested">Requested</option>', $html);
        $this->assertStringContainsString('<option value="active">Active Queue (Pending / In Progress)</option>', $html);
        $this->assertStringContainsString('id="admin-booking-sort-select"', $html);
        $this->assertStringContainsString('Requested Oldest → Completed Last (Default)', $html);

        $jsContent = file_get_contents(public_path('js/app.js')) ?: '';
        $this->assertStringContainsString('initUniversalTableTools', $jsContent);
        $this->assertStringContainsString('applyTableColumnFilters', $jsContent);

        $cssContent = file_get_contents(public_path('css/styles.css')) ?: '';
        $this->assertStringContainsString('.table-th-sortable', $cssContent);
        $this->assertStringContainsString('.table-col-filter-input', $cssContent);
    }
}
