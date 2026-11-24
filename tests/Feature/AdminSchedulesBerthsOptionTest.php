<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSchedulesBerthsOptionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('role', 'admin')->first();
        $this->customer = User::where('role', 'customer')->first();
    }

    public function test_schedules_and_berths_option_is_initially_off_by_default(): void
    {
        // Assert setting in database defaults to '0' (OFF)
        $this->assertEquals('0', PlatformSetting::get('enable_schedules_and_berths', '0'));
        $this->assertFalse(PlatformSetting::isSchedulesAndBerthsEnabled());
    }

    public function test_customer_and_guest_do_not_see_schedules_nav_when_off(): void
    {
        // 1. Guest request
        $guestRes = $this->get('/');
        $guestRes->assertStatus(200);
        $guestRes->assertSee('Outstation Cabs');
        // Main nav button and mobile dock button for schedules should NOT be rendered
        $guestRes->assertDontSee('id="nav-tab-btn-schedules"', false);
        $guestRes->assertDontSee('id="mobile-dock-btn-schedules"', false);

        // 2. Customer request
        $customerRes = $this->actingAs($this->customer)->get('/');
        $customerRes->assertStatus(200);
        $customerRes->assertSee('Outstation Cabs');
        $customerRes->assertDontSee('id="nav-tab-btn-schedules"', false);
        $customerRes->assertDontSee('id="mobile-dock-btn-schedules"', false);
        $customerRes->assertDontSee('id="dropdown-item-schedules"', false);
    }

    public function test_customer_cannot_book_bus_or_berths_when_off(): void
    {
        $payload = [
            'service_type' => 'bus',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Swargate Bay 4',
            'drop_location' => 'Panjim KTC',
            'customer_name' => 'Pooja Hegde',
            'customer_phone' => '+91 91234 56789',
            'selected_units' => ['L1', 'L4'],
            'passengers' => [
                ['name' => 'Pooja Hegde', 'age' => 28, 'gender' => 'female'],
                ['name' => 'Friend', 'age' => 27, 'gender' => 'female'],
            ],
        ];

        $response = $this->actingAs($this->customer)->postJson('/api/bookings', $payload);
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Schedules and berth bookings are currently unavailable.',
            ]);
    }

    public function test_admin_settings_api_returns_feature_setting(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/settings');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'settings' => [
                    'enable_schedules_and_berths' => '0',
                ],
            ]);
    }

    public function test_admin_can_toggle_schedules_and_berths_on(): void
    {
        $payload = [
            'feature' => 'schedules_and_berths',
            'enabled' => true,
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/admin/settings/toggle-feature', $payload);
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'enabled' => true,
                'enable_schedules_and_berths' => '1',
            ]);

        $this->assertEquals('1', PlatformSetting::get('enable_schedules_and_berths'));
        $this->assertTrue(PlatformSetting::isSchedulesAndBerthsEnabled());

        // When ON, customer sees Schedules & Berths
        $customerRes = $this->actingAs($this->customer)->get('/');
        $customerRes->assertStatus(200);
        $customerRes->assertSee('id="nav-tab-btn-schedules"', false);
        $customerRes->assertSee('id="mobile-dock-btn-schedules"', false);
        $customerRes->assertSee('id="dropdown-item-schedules"', false);
        $customerRes->assertSee('Schedules & Berths', false);
    }

    public function test_non_admin_cannot_toggle_feature(): void
    {
        $payload = [
            'feature' => 'schedules_and_berths',
            'enabled' => true,
        ];

        $response = $this->actingAs($this->customer)->postJson('/api/admin/settings/toggle-feature', $payload);
        $response->assertStatus(403);
    }

    public function test_customer_can_book_bus_when_feature_is_turned_on(): void
    {
        // Turn feature ON
        PlatformSetting::set('enable_schedules_and_berths', '1');

        $payload = [
            'service_type' => 'bus',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Swargate Bay 4',
            'drop_location' => 'Panjim KTC',
            'customer_name' => 'Pooja Hegde',
            'customer_phone' => '+91 91234 56789',
            'selected_units' => ['L1', 'L4'],
            'passengers' => [
                ['name' => 'Pooja Hegde', 'age' => 28, 'gender' => 'female'],
                ['name' => 'Friend', 'age' => 27, 'gender' => 'female'],
            ],
        ];

        $response = $this->actingAs($this->customer)->postJson('/api/bookings', $payload);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('bookings', [
            'customer_name' => 'Pooja Hegde',
            'service_type' => 'bus',
        ]);
    }

    public function test_admin_can_toggle_schedules_and_berths_back_off(): void
    {
        // Start ON
        PlatformSetting::set('enable_schedules_and_berths', '1');

        $payload = [
            'feature' => 'schedules_and_berths',
            'enabled' => false,
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/admin/settings/toggle-feature', $payload);
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'enabled' => false,
                'enable_schedules_and_berths' => '0',
            ]);

        $this->assertEquals('0', PlatformSetting::get('enable_schedules_and_berths'));
        $this->assertFalse(PlatformSetting::isSchedulesAndBerthsEnabled());
    }
}
