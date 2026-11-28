<?php

namespace Tests\Feature;

use App\Models\BiddingSession;
use App\Models\Booking;
use App\Models\CarType;
use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\PlatformSetting;
use App\Models\RouteFare;
use App\Models\User;
use App\Notifications\PlatformAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarType::create([
            'category_key' => 'sedan',
            'name' => 'Sedan',
            'models' => 'Dzire, Etios',
            'rate_per_km' => 13,
            'extra_km_rate' => 13,
            'is_active' => true,
            'order_index' => 1,
        ]);

        CarType::create([
            'category_key' => 'suv',
            'name' => 'SUV',
            'models' => 'Innova, Ertiga',
            'rate_per_km' => 18,
            'extra_km_rate' => 18,
            'is_active' => true,
            'order_index' => 2,
        ]);

        RouteFare::create([
            'from_city' => 'Kolkata',
            'to_city' => 'Digha',
            'from_lat' => 22.5726,
            'from_lng' => 88.3639,
            'to_lat' => 21.6266,
            'to_lng' => 87.5074,
            'distance_km' => 185,
            'estimated_time' => '4 hrs 30 mins',
            'base_toll' => 250,
            'sedan_fare' => 2900,
            'suv_fare' => 3800,
            'roundtrip_discount_pct' => 10,
            'is_active' => true,
        ]);

        PlatformSetting::set('bidding_min_service_charge_pct', '15');
        PlatformSetting::set('bidding_max_service_charge_pct', '75');
    }

    public function test_booking_creation_sends_mail_and_app_notifications_to_customer_admin_and_verified_drivers(): void
    {
        Notification::fake();

        $customer = User::factory()->create([
            'role' => 'customer',
            'email' => 'rider@example.com',
            'phone' => '9876543210',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'ops@example.com',
            'phone' => '9000000001',
        ]);

        $driverUser = User::factory()->create([
            'role' => 'driver',
            'email' => 'driver@example.com',
            'phone' => '9822011920',
        ]);

        Driver::create([
            'name' => 'Rajesh Kumar',
            'phone' => '9822011920',
            'vehicle_model' => 'Toyota Innova',
            'vehicle_plate' => 'MH12AB1234',
            'fleet_category' => 'suv',
            'is_verified' => true,
            'is_online' => true,
        ]);

        $response = $this->actingAs($customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Kolkata',
            'to_city' => 'Digha',
            'pickup_location' => 'Salt Lake Sector V',
            'drop_location' => 'New Digha Sea Beach',
            'customer_name' => 'Aarav Sen',
            'customer_phone' => '9876543210',
            'customer_email' => 'custom.guest@example.com',
            'fleet_category' => 'suv',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        Notification::assertSentTo($customer, PlatformAlertNotification::class, function (PlatformAlertNotification $n) {
            return $n->event === 'booking.created' && $n->sendEmail === true;
        });

        Notification::assertSentTo($admin, PlatformAlertNotification::class, function (PlatformAlertNotification $n) {
            return $n->event === 'booking.created' && $n->sendEmail === true;
        });

        Notification::assertSentTo($driverUser, PlatformAlertNotification::class, function (PlatformAlertNotification $n) {
            return $n->event === 'bidding.opened';
        });

        Notification::assertSentOnDemand(PlatformAlertNotification::class, function ($notification, $channels, $notifiable) {
            return $notifiable instanceof AnonymousNotifiable
                && ($notifiable->routes['mail'] ?? null) === 'custom.guest@example.com'
                && in_array('mail', $channels, true);
        });
    }

    public function test_booking_allotment_status_trip_lifecycle_and_vehicle_events_create_database_notifications(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'email' => 'customer.life@example.com',
            'phone' => '9811112222',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin.life@example.com',
            'phone' => '9800000001',
        ]);

        $driverUser = User::factory()->create([
            'role' => 'driver',
            'email' => 'driver.life@example.com',
            'phone' => '9833334444',
        ]);

        $driver = Driver::create([
            'name' => 'Vikram Singh',
            'phone' => '9833334444',
            'vehicle_model' => 'Dzire',
            'vehicle_plate' => 'WB02CD5678',
            'fleet_category' => 'sedan',
            'is_verified' => true,
            'is_online' => true,
            'wallet_balance' => 0,
        ]);

        // 1. Customer books a cab
        $bookRes = $this->actingAs($customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Kolkata',
            'to_city' => 'Digha',
            'pickup_location' => 'Howrah Station',
            'drop_location' => 'Old Digha',
            'customer_name' => 'Test Rider',
            'customer_phone' => '9811112222',
            'customer_email' => 'customer.life@example.com',
            'fleet_category' => 'sedan',
        ]);
        $bookRes->assertOk();
        $bookingId = $bookRes->json('booking.id');
        $otp = (string) $bookRes->json('booking.start_otp');

        // 2. Driver places a bid
        $session = BiddingSession::where('booking_id', $bookingId)->firstOrFail();
        $this->actingAs($driverUser)->postJson("/api/bidding/{$session->token}/bids", [
            'amount' => (float) $session->start_amount - 50,
            'agreed_terms' => true,
        ])->assertOk();

        // 3. Admin allots driver (awards auction)
        $this->actingAs($admin)->postJson("/api/admin/bookings/{$bookingId}/allot-driver", [
            'driver_id' => $driver->id,
        ])->assertOk();

        // 4. Driver verifies OTP -> Trip started
        $this->actingAs($driverUser)->postJson("/api/driver/trips/{$bookingId}/verify-otp", [
            'otp' => $otp,
        ])->assertOk();

        // 5. Driver completes trip -> Payout credited
        $this->actingAs($driverUser)->postJson("/api/driver/trips/{$bookingId}/complete")
            ->assertOk();

        // 6. Vehicle approval notification
        $vehicle = DriverVehicle::create([
            'driver_id' => $driver->id,
            'user_id' => $driverUser->id,
            'driver_name' => $driver->name,
            'driver_phone' => $driver->phone,
            'vehicle_model' => 'Ertiga',
            'vehicle_plate' => 'WB02EF9999',
            'fleet_category' => 'suv',
            'seating_capacity' => 6,
            'status' => 'Pending',
            'is_active' => false,
        ]);
        $this->actingAs($admin)->postJson("/api/admin/vehicles/{$vehicle->id}/approve")->assertOk();

        $customerEvents = $customer->notifications()->get()->pluck('data.event')->all();
        $this->assertContains('booking.created', $customerEvents);
        $this->assertContains('booking.driver_assigned', $customerEvents);
        $this->assertContains('trip.started', $customerEvents);
        $this->assertContains('trip.completed', $customerEvents);

        $driverEvents = $driverUser->notifications()->get()->pluck('data.event')->all();
        $this->assertContains('bidding.opened', $driverEvents);
        $this->assertContains('booking.driver_assigned', $driverEvents);
        $this->assertContains('trip.completed', $driverEvents);
        $this->assertContains('vehicle.approved', $driverEvents);

        $adminEvents = $admin->notifications()->get()->pluck('data.event')->all();
        $this->assertContains('booking.created', $adminEvents);
        $this->assertContains('bidding.bid_placed', $adminEvents);
        $this->assertContains('trip.started', $adminEvents);
        $this->assertContains('trip.completed', $adminEvents);
    }

    public function test_notifications_api_lists_marks_read_and_protects_other_users(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $otherUser = User::factory()->create(['role' => 'customer']);

        $customer->notify(new PlatformAlertNotification(
            event: 'booking.created',
            title: 'Booking Confirmed: ICW-TEST01',
            message: 'Trip recorded.',
            sendEmail: false,
        ));
        $customer->notify(new PlatformAlertNotification(
            event: 'booking.driver_assigned',
            title: 'Driver Assigned: ICW-TEST01',
            message: 'Chauffeur allotted.',
            sendEmail: false,
        ));

        $listRes = $this->actingAs($customer)->getJson('/api/notifications');
        $listRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 2);

        $firstId = $listRes->json('notifications.0.id');

        // Another user cannot mark customer's notification read
        $this->actingAs($otherUser)->postJson("/api/notifications/{$firstId}/read")
            ->assertStatus(404);

        // Customer marks one read
        $this->actingAs($customer)->postJson("/api/notifications/{$firstId}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        // Customer marks all read
        $this->actingAs($customer)->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $customer->fresh()->unreadNotifications()->count());
    }

    public function test_invalid_smtp_transport_does_not_fail_booking_or_in_app_notification(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 59999,
        ]);

        $customer = User::factory()->create([
            'role' => 'customer',
            'email' => 'smtpfail@example.com',
            'phone' => '9812345678',
        ]);

        $res = $this->actingAs($customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Kolkata',
            'to_city' => 'Digha',
            'pickup_location' => 'Esplanade',
            'drop_location' => 'Digha Gate',
            'customer_name' => 'Resilient Rider',
            'customer_phone' => '9812345678',
            'customer_email' => 'smtpfail@example.com',
            'fleet_category' => 'sedan',
        ]);

        $res->assertOk()->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, $customer->fresh()->notifications()->count());
    }

    public function test_customer_notifications_include_booking_portion_focus_and_deep_links(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'email' => 'focus.rider@example.com',
            'phone' => '9876500111',
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'focus.admin@example.com',
            'phone' => '9800000111',
        ]);
        $driverUser = User::factory()->create([
            'role' => 'driver',
            'email' => 'focus.driver@example.com',
            'phone' => '9833300111',
        ]);
        $driver = Driver::create([
            'name' => 'Ramesh Chauffeur',
            'phone' => '9833300111',
            'vehicle_model' => 'Innova Crysta',
            'vehicle_plate' => 'WB06XY1234',
            'fleet_category' => 'suv',
            'is_verified' => true,
            'is_online' => true,
            'wallet_balance' => 0,
        ]);

        // 1. Booking created -> focus 'otp'
        $bookRes = $this->actingAs($customer)->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Kolkata',
            'to_city' => 'Digha',
            'pickup_location' => 'Park Street',
            'drop_location' => 'Digha Beach',
            'customer_name' => 'Focus Rider',
            'customer_phone' => '9876500111',
            'customer_email' => 'focus.rider@example.com',
            'fleet_category' => 'suv',
        ])->assertOk();

        $bookingId = $bookRes->json('booking.id');
        $bookingRef = $bookRes->json('booking.booking_reference');
        $otp = (string) $bookRes->json('booking.start_otp');

        // 2. Driver allotted -> focus 'driver'
        $this->actingAs($admin)->postJson("/api/admin/bookings/{$bookingId}/allot-driver", [
            'driver_id' => $driver->id,
        ])->assertOk();

        // 3. Trip started -> focus 'status'
        $this->actingAs($driverUser)->postJson("/api/driver/trips/{$bookingId}/verify-otp", [
            'otp' => $otp,
        ])->assertOk();

        // 4. Trip completed -> focus 'receipt'
        $this->actingAs($driverUser)->postJson("/api/driver/trips/{$bookingId}/complete")
            ->assertOk();

        $listRes = $this->actingAs($customer)->getJson('/api/notifications')->assertOk();
        $items = collect($listRes->json('notifications'));

        $byEvent = $items->keyBy('event');

        $this->assertSame('otp', $byEvent['booking.created']['focus'] ?? null);
        $this->assertSame($bookingRef, $byEvent['booking.created']['meta']['booking_reference'] ?? null);
        $this->assertStringContainsString("booking={$bookingRef}&focus=otp", (string) ($byEvent['booking.created']['action_url'] ?? ''));

        $this->assertSame('driver', $byEvent['booking.driver_assigned']['focus'] ?? null);
        $this->assertStringContainsString("booking={$bookingRef}&focus=driver", (string) ($byEvent['booking.driver_assigned']['action_url'] ?? ''));

        $this->assertSame('status', $byEvent['trip.started']['focus'] ?? null);
        $this->assertStringContainsString("booking={$bookingRef}&focus=status", (string) ($byEvent['trip.started']['action_url'] ?? ''));

        $this->assertSame('receipt', $byEvent['trip.completed']['focus'] ?? null);
        $this->assertStringContainsString("booking={$bookingRef}&focus=receipt", (string) ($byEvent['trip.completed']['action_url'] ?? ''));

        // Legacy notification without meta.focus should still expose inferred focus
        $customer->notify(new PlatformAlertNotification(
            event: 'booking.payment_verified',
            title: "Payment Verified: {$bookingRef}",
            message: 'Paid.',
            meta: ['booking_reference' => $bookingRef, 'tab' => 'my-bookings'],
            sendEmail: false,
        ));

        $refreshed = collect($this->actingAs($customer)->getJson('/api/notifications')->json('notifications'))
            ->firstWhere('event', 'booking.payment_verified');
        $this->assertSame('payment', $refreshed['focus'] ?? null);
        $this->assertSame('payment', $refreshed['meta']['focus'] ?? null);

        // Deep-link URL opens My Trips with booking portion markers rendered
        $page = $this->actingAs($customer)->get("/?tab=my-bookings&booking={$bookingRef}&focus=driver");
        $page->assertOk();
        $html = $page->getContent();
        $this->assertMatchesRegularExpression('/<section id="tab-my-bookings" class="tab-pane active"/', $html);
        $this->assertStringContainsString("id=\"trip-card-{$bookingRef}\"", $html);
        $this->assertStringContainsString("data-booking-ref=\"{$bookingRef}\"", $html);
        $this->assertStringContainsString('data-booking-part="driver"', $html);
        $this->assertStringContainsString('data-booking-part="payment receipt"', $html);

        // Malformed booking query parameter is ignored safely
        $badPage = $this->actingAs($customer)->get('/?booking="><script>alert(1)</script>');
        $badPage->assertOk();
        $this->assertDoesNotMatchRegularExpression('/<section id="tab-my-bookings" class="tab-pane active"/', $badPage->getContent());
    }
}
