<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Driver;
use App\Models\Booking;

class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_customer_sees_only_customer_options(): void
    {
        $customer = User::where('role', 'customer')->first();
        $this->assertNotNull($customer);

        $response = $this->actingAs($customer)->get('/');
        $response->assertStatus(200);

        // Customer sees their relevant options
        $response->assertSee('Outstation Cabs');
        $response->assertSee('Schedules & Berths', false);
        $response->assertSee('My Trips');
        $response->assertSee('My Outstation Trips & Digital Tickets', false);

        // Customer DOES NOT see admin or driver controls
        $response->assertDontSee('Admin Ops');
        $response->assertDontSee('data-target="admin"', false);
        $response->assertDontSee('data-target="driver-wallet"', false);
    }

    public function test_driver_sees_only_driver_options(): void
    {
        $driver = User::where('role', 'driver')->first();
        $this->assertNotNull($driver);

        $response = $this->actingAs($driver)->get('/');
        $response->assertStatus(200);

        // Driver sees their relevant options
        $response->assertSee('Live Bids & Auctions', false);
        $response->assertSee('My Assigned Trips');
        $response->assertSee('Driver Wallet');
        $response->assertSee('Chauffeur Wallet & Instant Payouts', false);

        // Driver DOES NOT see admin panel or admin ops
        $response->assertDontSee('Admin Ops');
        $response->assertDontSee('data-target="admin"', false);
    }

    public function test_admin_sees_admin_panel_and_supervisor_options(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get('/');
        $response->assertStatus(200);

        $response->assertSee('Admin Panel');
        $response->assertSee('Admin Ops');
        $response->assertSee('role-admin-mode', false);
        $response->assertSee('admin-active-view', false);
    }

    public function test_driver_can_verify_start_otp_and_complete_trip(): void
    {
        $driverUser = User::where('role', 'driver')->first();
        $driverModel = Driver::first();
        $this->assertNotNull($driverUser);
        $this->assertNotNull($driverModel);

        $booking = Booking::create([
            'booking_reference' => 'ICW-TEST-OTP',
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'route_summary' => 'Pune → Mumbai',
            'pickup_location' => 'Baner, Pune',
            'drop_location' => 'Bandra, Mumbai',
            'customer_name' => 'Aditi Rao',
            'customer_phone' => '+91 98999 11111',
            'base_fare' => 2800,
            'toll_fare' => 320,
            'addon_fare' => 0,
            'discount_fare' => 0,
            'total_amount' => 3120,
            'status' => 'Driver Assigned',
            'start_otp' => '5566',
            'assigned_driver_id' => $driverModel->id
        ]);

        // Wrong OTP fails
        $failRes = $this->actingAs($driverUser)->postJson("/api/driver/trips/{$booking->id}/verify-otp", [
            'otp' => '0000'
        ]);
        $failRes->assertStatus(422);

        // Correct OTP succeeds
        $successRes = $this->actingAs($driverUser)->postJson("/api/driver/trips/{$booking->id}/verify-otp", [
            'otp' => '5566'
        ]);
        $successRes->assertStatus(200);
        $successRes->assertJson([
            'success' => true,
            'booking' => [
                'status' => 'Started'
            ]
        ]);

        // Complete trip
        $completeRes = $this->actingAs($driverUser)->postJson("/api/driver/trips/{$booking->id}/complete");
        $completeRes->assertStatus(200);
        $completeRes->assertJson([
            'success' => true,
            'booking' => [
                'status' => 'Completed'
            ]
        ]);
    }

    public function test_guest_sees_clean_distraction_free_heading(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Guest sees clean customer booking options & minimal Sign In
        $response->assertSee('Outstation Cabs');
        $response->assertSee('Schedules & Berths', false);
        $response->assertSee('Sign In');

        // Guest DOES NOT see clutter in heading
        $response->assertDontSee('Admin Ops');
        $response->assertDontSee('guest-role-mini-pill', false);
        $response->assertDontSee('data-target="admin"', false);
    }
}
