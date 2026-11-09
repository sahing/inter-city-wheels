<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Driver;
use App\Models\Booking;
use App\Models\ServiceLocation;
use App\Models\BerthRoute;
use App\Models\SeatLayout;
use App\Models\DriverVehicle;

class EndToEndAdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $driverUser;
    protected Driver $driverProfile;
    protected User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::where('role', 'admin')->first();
        $this->driverUser = User::where('role', 'driver')->first();
        $this->driverProfile = Driver::first();
        $this->customerUser = User::where('role', 'customer')->first();
    }

    /**
     * 1. Test Admin Service Location CRUD & Status Toggle
     */
    public function test_admin_service_location_full_lifecycle(): void
    {
        // CREATE
        $response = $this->actingAs($this->admin)->postJson('/api/admin/locations', [
            'name' => 'Nagpur Junction Hub',
            'state' => 'Maharashtra',
            'latitude' => 21.1458,
            'longitude' => 79.0882,
            'airport_code' => 'NAG',
            'has_airport_service' => true,
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $locationId = $response->json('location.id');
        $this->assertDatabaseHas('service_locations', [
            'id' => $locationId,
            'name' => 'Nagpur Junction Hub',
            'airport_code' => 'NAG'
        ]);

        // READ / SHOW
        $showRes = $this->actingAs($this->admin)->getJson("/api/admin/locations/{$locationId}");
        $showRes->assertStatus(200)
            ->assertJsonPath('location.name', 'Nagpur Junction Hub');

        // UPDATE
        $updateRes = $this->actingAs($this->admin)->putJson("/api/admin/locations/{$locationId}", [
            'name' => 'Nagpur Metro & Transit Hub',
            'state' => 'Maharashtra',
            'latitude' => 21.1458,
            'longitude' => 79.0882,
            'airport_code' => 'NAG',
            'has_airport_service' => true,
            'is_active' => true,
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('location.name', 'Nagpur Metro & Transit Hub');

        // TOGGLE STATUS
        $toggleRes = $this->actingAs($this->admin)->postJson("/api/admin/locations/{$locationId}/toggle");
        $toggleRes->assertStatus(200)
            ->assertJsonPath('location.is_active', false);

        // DELETE
        $delRes = $this->actingAs($this->admin)->deleteJson("/api/admin/locations/{$locationId}");
        $delRes->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('service_locations', ['id' => $locationId]);
    }

    /**
     * 2. Test Admin Berth Route Setup & Customization
     */
    public function test_admin_berth_route_full_lifecycle(): void
    {
        // CREATE
        $response = $this->actingAs($this->admin)->postJson('/api/admin/berth-routes', [
            'from_city' => 'Pune',
            'to_city' => 'Bengaluru',
            'operator_name' => 'Deccan Sleeper Deluxe',
            'coach_code' => '9988',
            'vehicle_type' => 'Volvo 9600 Multi-Axle Berth',
            'departure_time' => '07:00 PM',
            'arrival_time' => '08:30 AM',
            'base_price' => 1400.00,
            'total_berths' => 28,
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $routeId = $response->json('route.id');
        $this->assertDatabaseHas('berth_routes', [
            'coach_code' => '9988',
            'from_city' => 'Pune',
            'to_city' => 'Bengaluru'
        ]);

        // UPDATE
        $updateRes = $this->actingAs($this->admin)->putJson("/api/admin/berth-routes/{$routeId}", [
            'from_city' => 'Pune',
            'to_city' => 'Bengaluru',
            'operator_name' => 'Deccan Sleeper Deluxe (Superfast)',
            'coach_code' => '9988',
            'vehicle_type' => 'Volvo 9600 Multi-Axle Berth',
            'departure_time' => '06:30 PM',
            'arrival_time' => '07:45 AM',
            'base_price' => 1450.00,
            'total_berths' => 28,
            'is_active' => true,
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('route.base_price', 1450);

        // TOGGLE STATUS
        $toggleRes = $this->actingAs($this->admin)->postJson("/api/admin/berth-routes/{$routeId}/toggle");
        $toggleRes->assertStatus(200)
            ->assertJsonPath('route.is_active', false);
    }

    /**
     * 3. Test Per-Vehicle Berth and Seat Customizer
     */
    public function test_admin_seat_layout_customizer(): void
    {
        // Add Berth L1
        $resL1 = $this->actingAs($this->admin)->postJson('/api/admin/berths', [
            'coach_code' => '9988',
            'operator_name' => 'Deccan Sleeper Deluxe',
            'deck' => 'lower',
            'berth_number' => 'L1',
            'berth_type' => 'window',
            'price' => 1450.00,
            'is_ladies_quota' => false,
            'is_booked' => false,
        ]);
        $resL1->assertStatus(200)->assertJson(['success' => true]);
        $berthId = $resL1->json('berth.id');

        // Add Berth L2 (Ladies Quota)
        $resL2 = $this->actingAs($this->admin)->postJson('/api/admin/berths', [
            'coach_code' => '9988',
            'operator_name' => 'Deccan Sleeper Deluxe',
            'deck' => 'lower',
            'berth_number' => 'L2',
            'berth_type' => 'aisle',
            'price' => 1400.00,
            'is_ladies_quota' => true,
            'is_booked' => false,
        ]);
        $resL2->assertStatus(200)->assertJson(['success' => true]);

        // UPDATE Berth L1 price
        $upRes = $this->actingAs($this->admin)->putJson("/api/admin/berths/{$berthId}", [
            'coach_code' => '9988',
            'operator_name' => 'Deccan Sleeper Deluxe',
            'deck' => 'lower',
            'berth_number' => 'L1',
            'berth_type' => 'window',
            'price' => 1500.00,
            'is_ladies_quota' => false,
            'is_booked' => false,
        ]);
        $upRes->assertStatus(200)->assertJsonPath('berth.price', 1500);

        // Filter berths by coach
        $listRes = $this->actingAs($this->admin)->getJson('/api/admin/berths?coach=9988');
        $listRes->assertStatus(200);
        $this->assertCount(2, $listRes->json('berths'));
    }

    /**
     * 4. Test Driver Vehicle Listing and Admin Approval Workflow
     */
    public function test_driver_vehicle_listing_approval_and_rejection(): void
    {
        // Driver registers vehicle
        $postRes = $this->actingAs($this->driverUser)->postJson('/api/driver/vehicles', [
            'driver_id' => $this->driverProfile->id,
            'driver_name' => 'Ramesh Patil',
            'driver_phone' => '+91 98220 11920',
            'vehicle_model' => 'Tata Harrier Fearless Dark',
            'fleet_category' => 'suv',
            'vehicle_plate' => 'MH 12 XP 5566',
            'seating_capacity' => 5,
            'berth_capacity' => 0,
            'fuel_type' => 'Diesel',
            'model_year' => '2024',
            'rc_number' => 'RC-MH12-556677',
        ]);

        $postRes->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('vehicle.status', 'Pending');

        $vehId = $postRes->json('vehicle.id');

        // Admin approves vehicle
        $appRes = $this->actingAs($this->admin)->postJson("/api/admin/vehicles/{$vehId}/approve");
        $appRes->assertStatus(200)
            ->assertJsonPath('vehicle.status', 'Approved')
            ->assertJsonPath('vehicle.is_active', true);

        // Driver lists another vehicle for rejection test
        $rejPost = $this->actingAs($this->driverUser)->postJson('/api/driver/vehicles', [
            'driver_name' => 'Ramesh Patil',
            'driver_phone' => '+91 98220 11920',
            'vehicle_model' => 'Old WagonR 2011',
            'fleet_category' => 'sedan',
            'vehicle_plate' => 'MH 14 ZZ 1122',
            'seating_capacity' => 4,
            'berth_capacity' => 0,
            'fuel_type' => 'Petrol',
            'model_year' => '2011',
            'rc_number' => 'RC-MH14-OLD-01',
        ]);

        $oldVehId = $rejPost->json('vehicle.id');

        // Admin rejects vehicle with audit note
        $rejectRes = $this->actingAs($this->admin)->postJson("/api/admin/vehicles/{$oldVehId}/reject", [
            'admin_notes' => 'Vehicle is older than 8 years, taxi fitness standards not satisfied.'
        ]);
        $rejectRes->assertStatus(200)
            ->assertJsonPath('vehicle.status', 'Rejected')
            ->assertJsonPath('vehicle.admin_notes', 'Vehicle is older than 8 years, taxi fitness standards not satisfied.');
    }

    /**
     * 5. Test Full Booking Lifecycle: Create, Edit, Allot Chauffeur, Tax Invoice, Start OTP, Payout
     */
    public function test_booking_lifecycle_allotment_invoice_and_driver_flow(): void
    {
        // 1. Admin creates a booking
        $createRes = $this->actingAs($this->admin)->postJson('/api/admin/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'fleet_category' => 'suv',
            'from_city' => 'Pune',
            'to_city' => 'Mumbai',
            'route_summary' => 'Pune → Mumbai',
            'pickup_location' => 'Koregaon Park, Lane 7',
            'drop_location' => 'Bandra Kurla Complex, Tower B',
            'customer_name' => 'Aditya Singhal',
            'customer_phone' => '+91 99887 76655',
            'customer_email' => 'aditya@example.com',
            'base_fare' => 2800.00,
            'toll_fare' => 320.00,
            'addon_fare' => 150.00,
            'discount_fare' => 0.00,
            'total_amount' => 3270.00,
            'status' => 'Confirmed',
            'start_otp' => '5182'
        ]);

        $createRes->assertStatus(201)->assertJson(['success' => true]);
        $booking = $createRes->json('booking');
        $bookingId = $booking['id'];

        // 2. Admin edits booking
        $editRes = $this->actingAs($this->admin)->putJson("/api/admin/bookings/{$bookingId}", [
            'pickup_location' => 'Koregaon Park, North Main Road',
            'drop_location' => 'BKC Trident Hotel',
            'status' => 'Confirmed',
            'total_amount' => 3270.00
        ]);
        $editRes->assertStatus(200)->assertJson(['success' => true]);

        // 3. Admin allots driver
        $allotRes = $this->actingAs($this->admin)->postJson("/api/admin/bookings/{$bookingId}/allot-driver", [
            'driver_id' => $this->driverProfile->id
        ]);
        $allotRes->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('booking.assigned_driver_id', $this->driverProfile->id)
            ->assertJsonPath('booking.status', 'Driver Assigned');

        // 4. Admin generates GST Tax Invoice
        $invRes = $this->actingAs($this->admin)->getJson("/api/admin/bookings/{$bookingId}/invoice");
        $invRes->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'booking',
                'invoice' => [
                    'invoice_number',
                    'invoice_date',
                    'company',
                    'customer',
                    'driver',
                    'charges' => [
                        'base_fare',
                        'toll_fare',
                        'taxable_amount',
                        'cgst',
                        'sgst',
                        'total_amount'
                    ]
                ]
            ]);

        // 5. Driver verifies ride start OTP
        $rideOtp = $booking['start_otp'];
        $otpRes = $this->actingAs($this->driverUser)->postJson("/api/driver/trips/{$bookingId}/verify-otp", [
            'otp' => $rideOtp
        ]);
        $otpRes->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('booking.status', 'Started');

        // 6. Driver completes trip
        $walletBefore = $this->driverProfile->fresh()->wallet_balance;
        $compRes = $this->actingAs($this->driverUser)->postJson("/api/driver/trips/{$bookingId}/complete");
        $compRes->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('booking.status', 'Completed');

        $walletAfter = $this->driverProfile->fresh()->wallet_balance;
        $this->assertGreaterThan($walletBefore, $walletAfter);
    }

    /**
     * 6. Test Admin User & Driver Directory
     */
    public function test_admin_user_directory_operations(): void
    {
        // Create new driver partner
        $userRes = $this->actingAs($this->admin)->postJson('/api/admin/users', [
            'name' => 'Satish Shinde',
            'email' => 'satish.shinde@example.com',
            'phone' => '+91 98333 44556',
            'role' => 'driver',
            'password' => 'secret123',
            'vehicle_model' => 'Maruti Suzuki Dzire VXi',
            'vehicle_plate' => 'MH 04 ER 9012',
            'fleet_category' => 'sedan',
        ]);

        $userRes->assertStatus(201)->assertJson(['success' => true]);
        $newUserId = $userRes->json('user.id');

        $this->assertDatabaseHas('users', ['email' => 'satish.shinde@example.com', 'role' => 'driver']);
        $this->assertDatabaseHas('drivers', ['vehicle_plate' => 'MH 04 ER 9012']);

        // Update user
        $upUserRes = $this->actingAs($this->admin)->putJson("/api/admin/users/{$newUserId}", [
            'name' => 'Satish Shinde (Senior Chauffeur)',
            'email' => 'satish.shinde@example.com',
            'phone' => '+91 98333 44556',
            'role' => 'driver',
        ]);
        $upUserRes->assertStatus(200)
            ->assertJsonPath('user.name', 'Satish Shinde (Senior Chauffeur)');

        // Delete user
        $delUserRes = $this->actingAs($this->admin)->deleteJson("/api/admin/users/{$newUserId}");
        $delUserRes->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseMissing('users', ['id' => $newUserId]);
    }
}
