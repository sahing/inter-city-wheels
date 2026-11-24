<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Admin endpoints now require an authenticated admin (role middleware).
        $this->actingAs(User::where('role', 'admin')->firstOrFail());
    }

    public function test_admin_can_list_all_bookings(): void
    {
        $response = $this->getJson('/api/admin/bookings');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'bookings' => [
                '*' => [
                    'id',
                    'booking_reference',
                    'customer_name',
                    'total_amount',
                    'status',
                    'start_otp',
                ],
            ],
        ]);
    }

    public function test_admin_can_manually_create_booking(): void
    {
        $payload = [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Kothrud, Pune',
            'drop_location' => 'Calangute, Goa',
            'customer_name' => 'Dr. A. P. J. Abdul Kalam',
            'customer_phone' => '+91 98220 99999',
            'customer_email' => 'kalam@isro.org',
            'base_fare' => 6500,
            'toll_fare' => 480,
            'addon_fare' => 350,
            'discount_fare' => 500,
        ];

        $response = $this->postJson('/api/admin/bookings', $payload);
        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'booking' => [
                'customer_name' => 'Dr. A. P. J. Abdul Kalam',
                'service_type' => 'cab',
                'total_amount' => 6830,
            ],
        ]);

        $this->assertDatabaseHas('bookings', [
            'customer_name' => 'Dr. A. P. J. Abdul Kalam',
            'total_amount' => 6830,
        ]);
    }

    public function test_admin_can_show_and_update_booking(): void
    {
        $booking = Booking::first();
        $this->assertNotNull($booking);

        $showRes = $this->getJson("/api/admin/bookings/{$booking->id}");
        $showRes->assertStatus(200);
        $showRes->assertJson([
            'success' => true,
            'booking' => [
                'id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
            ],
        ]);

        $updatePayload = [
            'customer_name' => 'Ratan Tata',
            'pickup_location' => 'Tata Motors Gate, Pune',
            'base_fare' => 5000,
            'toll_fare' => 400,
            'addon_fare' => 0,
            'discount_fare' => 0,
            'status' => 'Confirmed',
        ];

        $updateRes = $this->putJson("/api/admin/bookings/{$booking->id}", $updatePayload);
        $updateRes->assertStatus(200);
        $updateRes->assertJson([
            'success' => true,
            'booking' => [
                'customer_name' => 'Ratan Tata',
                'pickup_location' => 'Tata Motors Gate, Pune',
                'total_amount' => 5400,
            ],
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'customer_name' => 'Ratan Tata',
        ]);
    }

    public function test_admin_can_allot_driver_to_booking(): void
    {
        $booking = Booking::first();
        $driver = Driver::first();
        $this->assertNotNull($booking);
        $this->assertNotNull($driver);

        $response = $this->postJson("/api/admin/bookings/{$booking->id}/allot-driver", [
            'driver_id' => $driver->id,
            'status' => 'Driver Assigned',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'booking' => [
                'id' => $booking->id,
                'assigned_driver_id' => $driver->id,
                'status' => 'Driver Assigned',
            ],
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'assigned_driver_id' => $driver->id,
            'status' => 'Driver Assigned',
        ]);
    }

    public function test_admin_can_generate_tax_invoice(): void
    {
        $booking = Booking::first();
        $this->assertNotNull($booking);

        $response = $this->getJson("/api/admin/bookings/{$booking->id}/invoice");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'invoice' => [
                'invoice_number',
                'invoice_date',
                'company' => [
                    'name',
                    'gstin',
                    'hsn_sac',
                ],
                'customer' => [
                    'name',
                    'phone',
                    'pickup_location',
                    'drop_location',
                ],
                'charges' => [
                    'base_fare',
                    'toll_fare',
                    'taxable_amount',
                    'cgst',
                    'sgst',
                    'total_amount',
                ],
                'payment' => [
                    'status',
                    'transaction_ref',
                    'start_otp',
                ],
            ],
            'booking',
        ]);
    }
}
