<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverProfileAndLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_driver_can_get_and_update_profile()
    {
        $driverUser = User::where('role', 'driver')->first();
        if (! $driverUser) {
            $driverUser = User::factory()->create([
                'name' => 'Kishore Kumar',
                'email' => 'kishore.driver@intercitywheels.com',
                'role' => 'driver',
                'phone' => '+91 98300 11223',
            ]);
        }

        $driver = Driver::where('phone', $driverUser->phone)->first();
        if (! $driver) {
            $driver = Driver::create([
                'name' => $driverUser->name,
                'phone' => $driverUser->phone,
                'vehicle_model' => 'Maruti Suzuki Ertiga',
                'vehicle_plate' => 'WB 02 AB 1234',
                'fleet_category' => 'suv',
                'rating' => 4.92,
                'trips_count' => 520,
                'wallet_balance' => 12500,
                'is_verified' => true,
                'is_online' => true,
                'avatar' => '👨🏽‍✈️',
            ]);
        }

        // 1. Get Profile API
        $response = $this->actingAs($driverUser)->getJson('/api/driver/profile');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'profile' => [
                    'name' => $driver->name,
                    'vehicle_plate' => $driver->vehicle_plate,
                ],
            ]);

        // 2. Update Profile API
        $updatePayload = [
            'name' => 'Kishore K. Driver',
            'phone' => '+91 98300 99999',
            'vehicle_model' => 'Toyota Innova Hycross',
            'vehicle_plate' => 'WB 06 XY 9988',
            'fleet_category' => 'prime',
            'avatar' => '👨🏻‍✈️',
        ];

        $updateResponse = $this->actingAs($driverUser)->postJson('/api/driver/profile', $updatePayload);
        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Driver profile updated successfully.',
            ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'name' => 'Kishore K. Driver',
            'vehicle_plate' => 'WB 06 XY 9988',
            'avatar' => '👨🏻‍✈️',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $driverUser->id,
            'name' => 'Kishore K. Driver',
            'phone' => '+91 98300 99999',
        ]);
    }

    public function test_driver_can_toggle_duty_online_status()
    {
        $driverUser = User::where('role', 'driver')->first();
        $driver = Driver::firstOrCreate(
            ['phone' => $driverUser->phone ?? '+91 98220 11920'],
            [
                'name' => $driverUser->name,
                'vehicle_model' => 'Toyota Innova Crysta',
                'vehicle_plate' => 'MH 12 QX 4920',
                'fleet_category' => 'suv',
                'is_online' => true,
                'is_verified' => true,
            ]
        );

        $initialStatus = $driver->is_online;

        $response = $this->actingAs($driverUser)->postJson('/api/driver/toggle-online');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_online' => ! $initialStatus,
            ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'is_online' => ! $initialStatus,
        ]);
    }

    public function test_driver_panel_renders_uniform_navigation_and_edit_profile_modal()
    {
        $driverUser = User::where('role', 'driver')->first();

        $response = $this->actingAs($driverUser)->get('/');
        $response->assertStatus(200);

        // Assert Driver Master Header Banner components
        $response->assertSee('driver-master-banner', false);
        $response->assertSee('driver-duty-toggle-pill', false);
        $response->assertSee('openDriverEditProfileModal', false);
        $response->assertSee('Edit Profile', false);
        $response->assertSee('Register Car', false);

        // Assert Uniform Subnav Pills
        $response->assertSee('driver-subnav-bar', false);
        $response->assertSee('Live Bids & Auctions', false);
        $response->assertSee('My Assigned Trips', false);
        $response->assertSee('Wallet & Payouts', false);
        $response->assertSee('Chauffeur Profile & Fleet', false);

        // Assert Driver Profile Pane & Edit Profile Modal
        $response->assertSee('id="tab-driver-profile"', false);
        $response->assertSee('id="driver-edit-profile-modal"', false);
        $response->assertSee('id="driver-edit-profile-form"', false);
        $response->assertSee('Save Profile Changes', false);
    }
}
