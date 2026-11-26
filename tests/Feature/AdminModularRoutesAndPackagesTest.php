<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModularRoutesAndPackagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function getAdminUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@intercitywheels.com'],
            [
                'name' => 'Vikram Singhania (Ops Admin)',
                'password' => bcrypt('password123'),
                'role' => 'admin',
                'phone' => '+91 99000 00001',
            ]
        );
    }

    public function test_admin_panel_renders_modular_booking_types_tabs_and_modals(): void
    {
        $admin = $this->getAdminUser();

        $response = $this->actingAs($admin)->get('/');
        $response->assertStatus(200);

        // Modular tabs
        $response->assertSee('One-Way (Outstation Highway Routes)', false);
        $response->assertSee('Round-Trip Outstation', false);
        $response->assertSee('Local Hourly Packages', false);
        $response->assertSee('Airport Transit Hubs', false);
        $response->assertSee('Fare Rules & Policy Matrix', false);
        $response->assertSee('id="admin-bt-panel-oneway"', false);
        $response->assertSee('id="admin-bt-panel-roundtrip"', false);
        $response->assertDontSee('id="admin-bt-panel-car-types"', false);
        $response->assertDontSee('data-tab="car-types"', false);

        // Modals
        $response->assertSee('id="admin-cab-route-modal"', false);
        $response->assertSee('id="admin-local-package-modal"', false);
        $response->assertSee('id="admin-airport-hub-modal"', false);
    }

    public function test_admin_can_manage_local_packages_lifecycle(): void
    {
        $admin = $this->getAdminUser();

        // 1. List
        $resList = $this->actingAs($admin)->getJson('/api/admin/local-packages');
        $resList->assertStatus(200)->assertJsonPath('success', true);

        // 2. Create
        $resCreate = $this->actingAs($admin)->postJson('/api/admin/local-packages', [
            'package_code' => '6_60',
            'city' => 'Kolkata',
            'name' => '6 Hours / 60 KM (Business Tour)',
            'duration_hours' => 6,
            'included_km' => 60,
            'sedan_fare' => 1950.00,
            'suv_fare' => 2650.00,
            'extra_km_rate' => 14.50,
            'extra_hour_rate' => 160.00,
            'is_active' => true,
        ]);
        $resCreate->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('package.package_code', '6_60');

        $pkgId = $resCreate->json('package.id');

        // 3. Show & Update
        $resShow = $this->actingAs($admin)->getJson("/api/admin/local-packages/{$pkgId}");
        $resShow->assertStatus(200)->assertJsonPath('package.name', '6 Hours / 60 KM (Business Tour)');

        $resUpdate = $this->actingAs($admin)->putJson("/api/admin/local-packages/{$pkgId}", [
            'sedan_fare' => 2100.00,
            'suv_fare' => 2800.00,
            'name' => '6 Hours / 60 KM (Executive City Tour)',
        ]);
        $resUpdate->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('package.sedan_fare', 2100);

        // 4. Toggle Status
        $resToggle = $this->actingAs($admin)->postJson("/api/admin/local-packages/{$pkgId}/toggle");
        $resToggle->assertStatus(200)->assertJsonPath('is_active', false);

        // 5. Delete
        $resDelete = $this->actingAs($admin)->deleteJson("/api/admin/local-packages/{$pkgId}");
        $resDelete->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseMissing('local_packages', ['id' => $pkgId]);
    }

    public function test_admin_can_manage_airport_hubs_lifecycle(): void
    {
        $admin = $this->getAdminUser();

        // 1. List
        $resList = $this->actingAs($admin)->getJson('/api/admin/airport-hubs');
        $resList->assertStatus(200)->assertJsonPath('success', true);

        // 2. Create
        $resCreate = $this->actingAs($admin)->postJson('/api/admin/airport-hubs', [
            'airport_name' => 'Surat International Airport',
            'iata_code' => 'STV',
            'city' => 'Surat',
            'terminals' => 'Integrated Terminal Building',
            'pickup_surcharge' => 220.00,
            'drop_surcharge' => 120.00,
            'sedan_base_fare' => 850.00,
            'suv_base_fare' => 1350.00,
            'is_active' => true,
        ]);
        $resCreate->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('airport.iata_code', 'STV');

        $airportId = $resCreate->json('airport.id');

        // 3. Show & Update
        $resShow = $this->actingAs($admin)->getJson("/api/admin/airport-hubs/{$airportId}");
        $resShow->assertStatus(200)->assertJsonPath('airport.city', 'Surat');

        $resUpdate = $this->actingAs($admin)->putJson("/api/admin/airport-hubs/{$airportId}", [
            'pickup_surcharge' => 250.00,
            'sedan_base_fare' => 900.00,
        ]);
        $resUpdate->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('airport.pickup_surcharge', 250);

        // 4. Toggle Status
        $resToggle = $this->actingAs($admin)->postJson("/api/admin/airport-hubs/{$airportId}/toggle");
        $resToggle->assertStatus(200)->assertJsonPath('is_active', false);

        // 5. Delete
        $resDelete = $this->actingAs($admin)->deleteJson("/api/admin/airport-hubs/{$airportId}");
        $resDelete->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseMissing('airport_hubs', ['id' => $airportId]);
    }

    public function test_admin_can_manage_cab_route_custom_fares_and_roundtrip_discount(): void
    {
        $admin = $this->getAdminUser();

        $resCreate = $this->actingAs($admin)->postJson('/api/admin/cab-routes', [
            'from_city' => 'Kolkata',
            'to_city' => 'Digha',
            'distance_km' => 185,
            'estimated_time' => '4h 15m',
            'base_toll' => 150.00,
            'sedan_fare' => 2800.00,
            'suv_fare' => 3900.00,
            'roundtrip_discount_pct' => 10.00,
            'is_active' => true,
        ]);
        $resCreate->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('route.sedan_fare', 2800)
            ->assertJsonPath('route.roundtrip_discount_pct', 10);

        $routeId = $resCreate->json('route.id');

        $resUpdate = $this->actingAs($admin)->putJson("/api/admin/cab-routes/{$routeId}", [
            'sedan_fare' => 2950.00,
            'roundtrip_discount_pct' => 12.00,
        ]);
        $resUpdate->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('route.sedan_fare', 2950);

        // Clean up
        $this->actingAs($admin)->deleteJson("/api/admin/cab-routes/{$routeId}");
    }

    public function test_public_api_endpoints_return_active_packages_and_airports(): void
    {
        $resPkgs = $this->getJson('/api/local-packages');
        $resPkgs->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['packages']);

        $resAirports = $this->getJson('/api/airport-hubs');
        $resAirports->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['airports']);
    }

    public function test_optional_car_type_can_be_configured_under_each_booking_type_and_package(): void
    {
        $admin = $this->getAdminUser();

        // 1. Outstation Route with optional default_car_type
        $routeRes = $this->actingAs($admin)->postJson('/api/admin/cab-routes', [
            'from_city' => 'Pune',
            'to_city' => 'Nashik',
            'distance_km' => 210,
            'estimated_time' => '4h 30m',
            'default_car_type' => 'innova',
            'is_active' => true,
        ]);
        $routeRes->assertStatus(201)->assertJsonPath('route.default_car_type', 'innova');

        // 2. Local Package with optional default_car_type
        $pkgRes = $this->actingAs($admin)->postJson('/api/admin/local-packages', [
            'package_code' => '4_40_spec',
            'city' => 'Pune',
            'name' => '4 Hours / 40 KM Special',
            'duration_hours' => 4,
            'included_km' => 40,
            'sedan_fare' => 1200,
            'suv_fare' => 1700,
            'default_car_type' => 'sedan',
            'is_active' => true,
        ]);
        $pkgRes->assertStatus(201)->assertJsonPath('package.default_car_type', 'sedan');

        // 3. Airport Hub with optional default_car_type
        $hubRes = $this->actingAs($admin)->postJson('/api/admin/airport-hubs', [
            'airport_name' => 'Pune Lohegaon Airport',
            'iata_code' => 'PNQ',
            'city' => 'Pune',
            'default_car_type' => 'suv',
            'is_active' => true,
        ]);
        $hubRes->assertStatus(201)->assertJsonPath('airport.default_car_type', 'suv');

        // 4. Fare Rules & Policy Matrix per-booking-type car type preferences
        $ruleRes = $this->actingAs($admin)->postJson('/api/admin/settings/booking-type-car-types', [
            'oneway' => 'sedan',
            'roundtrip' => 'innova',
            'local' => 'hatchback',
            'airport' => 'suv',
        ]);
        $ruleRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('settings.default_car_type_oneway', 'sedan')
            ->assertJsonPath('settings.default_car_type_roundtrip', 'innova')
            ->assertJsonPath('settings.default_car_type_local', 'hatchback')
            ->assertJsonPath('settings.default_car_type_airport', 'suv');

        // 5. Clearing optional car type sets it back to null/empty
        $clearRes = $this->actingAs($admin)->putJson('/api/admin/cab-routes/'.$routeRes->json('route.id'), [
            'default_car_type' => '',
        ]);
        $clearRes->assertStatus(200)->assertJsonPath('route.default_car_type', null);

        // 6. Admin view renders selectors and badges
        $page = $this->actingAs($admin)->get('/');
        $page->assertStatus(200);
        $page->assertSee('id="admin-cab-route-default-car-type"', false);
        $page->assertSee('id="admin-local-pkg-default-car-type"', false);
        $page->assertSee('id="admin-airport-default-car-type"', false);
        $page->assertSee('id="rule-car-type-oneway"', false);
        $page->assertSee('id="rule-car-type-roundtrip"', false);
        $page->assertSee('id="rule-car-type-local"', false);
        $page->assertSee('id="rule-car-type-airport"', false);
        $page->assertSee('window.INITIAL_LOCAL_PACKAGES', false);
        $page->assertSee('window.INITIAL_AIRPORT_HUBS', false);
    }
}
