<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Driver;
use App\Models\ServiceLocation;
use App\Models\BerthRoute;
use App\Models\SeatLayout;
use App\Models\DriverVehicle;

class AdminFleetAndBerthManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $driverUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->adminUser = User::where('role', 'admin')->first();
        $this->driverUser = User::where('role', 'driver')->first();
    }

    public function test_admin_can_manage_service_locations(): void
    {
        // 1. List locations
        $res = $this->actingAs($this->adminUser)->getJson('/api/admin/locations');
        $res->assertStatus(200);
        $res->assertJsonStructure(['success', 'locations']);

        // 2. Create location
        $createRes = $this->actingAs($this->adminUser)->postJson('/api/admin/locations', [
            'name' => 'Ahmedabad',
            'state' => 'Gujarat',
            'latitude' => 23.0225,
            'longitude' => 72.5714,
            'airport_code' => 'AMD',
            'has_airport_service' => true,
            'is_active' => true,
        ]);
        $createRes->assertStatus(200);
        $createRes->assertJson(['success' => true]);

        $this->assertDatabaseHas('service_locations', [
            'name' => 'Ahmedabad',
            'airport_code' => 'AMD',
            'is_active' => true,
        ]);

        $loc = ServiceLocation::where('name', 'Ahmedabad')->first();

        // 3. Update location
        $updateRes = $this->actingAs($this->adminUser)->putJson("/api/admin/locations/{$loc->id}", [
            'name' => 'Ahmedabad Central',
            'state' => 'Gujarat',
            'latitude' => 23.0300,
            'longitude' => 72.5800,
            'airport_code' => 'AMD',
            'has_airport_service' => true,
            'is_active' => true,
        ]);
        $updateRes->assertStatus(200);
        $this->assertDatabaseHas('service_locations', ['name' => 'Ahmedabad Central']);

        // 4. Toggle status
        $toggleRes = $this->actingAs($this->adminUser)->postJson("/api/admin/locations/{$loc->id}/toggle");
        $toggleRes->assertStatus(200);
        $loc->refresh();
        $this->assertFalse($loc->is_active);

        // 5. Delete location
        $delRes = $this->actingAs($this->adminUser)->deleteJson("/api/admin/locations/{$loc->id}");
        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('service_locations', ['id' => $loc->id]);
    }

    public function test_admin_can_manage_berth_routes(): void
    {
        // 1. List berth routes
        $res = $this->actingAs($this->adminUser)->getJson('/api/admin/berth-routes');
        $res->assertStatus(200);
        $res->assertJsonStructure(['success', 'routes']);

        // 2. Create berth route
        $createRes = $this->actingAs($this->adminUser)->postJson('/api/admin/berth-routes', [
            'from_city' => 'Pune',
            'to_city' => 'Surat',
            'operator_name' => 'Gujarat Travels Sleeper',
            'coach_code' => 'GT-4401',
            'vehicle_type' => 'BharatBenz Luxury AC Berth',
            'departure_time' => '10:15 PM',
            'arrival_time' => '05:30 AM',
            'base_price' => 750,
            'total_berths' => 32,
            'is_active' => true,
        ]);
        $createRes->assertStatus(200);
        $this->assertDatabaseHas('berth_routes', [
            'coach_code' => 'GT-4401',
            'from_city' => 'Pune',
            'to_city' => 'Surat',
        ]);

        $route = BerthRoute::where('coach_code', 'GT-4401')->first();

        // 3. Update berth route
        $updateRes = $this->actingAs($this->adminUser)->putJson("/api/admin/berth-routes/{$route->id}", [
            'from_city' => 'Pune',
            'to_city' => 'Surat',
            'operator_name' => 'Gujarat Travels Royal Sleeper',
            'coach_code' => 'GT-4401',
            'vehicle_type' => 'BharatBenz Luxury AC Berth',
            'departure_time' => '10:30 PM',
            'arrival_time' => '05:45 AM',
            'base_price' => 800,
            'total_berths' => 32,
            'is_active' => true,
        ]);
        $updateRes->assertStatus(200);
        $this->assertDatabaseHas('berth_routes', ['base_price' => 800]);

        // 4. Toggle status
        $toggleRes = $this->actingAs($this->adminUser)->postJson("/api/admin/berth-routes/{$route->id}/toggle");
        $toggleRes->assertStatus(200);
        $route->refresh();
        $this->assertFalse($route->is_active);

        // 5. Delete berth route
        $delRes = $this->actingAs($this->adminUser)->deleteJson("/api/admin/berth-routes/{$route->id}");
        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('berth_routes', ['id' => $route->id]);
    }

    public function test_admin_can_manage_custom_berths_per_vehicle(): void
    {
        // 1. List berths for coach
        $res = $this->actingAs($this->adminUser)->getJson('/api/admin/berths?coach_code=5510');
        $res->assertStatus(200);
        $res->assertJsonStructure(['success', 'berths']);

        // 2. Add custom berth to vehicle / coach
        $createRes = $this->actingAs($this->adminUser)->postJson('/api/admin/berths', [
            'coach_code' => '5510',
            'operator_name' => 'Executive Urbania Transit',
            'deck' => 'lower',
            'berth_number' => 'B8',
            'berth_type' => 'window',
            'price' => 600,
            'is_ladies_quota' => true,
            'is_booked' => false,
        ]);
        $createRes->assertStatus(200);
        $this->assertDatabaseHas('seat_layouts', [
            'coach_code' => '5510',
            'berth_number' => 'B8',
            'is_ladies_quota' => true,
        ]);

        $berth = SeatLayout::where('coach_code', '5510')->where('berth_number', 'B8')->first();

        // 3. Update berth details
        $updateRes = $this->actingAs($this->adminUser)->putJson("/api/admin/berths/{$berth->id}", [
            'coach_code' => '5510',
            'operator_name' => 'Executive Urbania Transit',
            'deck' => 'lower',
            'berth_number' => 'B8',
            'berth_type' => 'double_sleeper',
            'price' => 900,
            'is_ladies_quota' => false,
            'is_booked' => true,
        ]);
        $updateRes->assertStatus(200);
        $this->assertDatabaseHas('seat_layouts', [
            'id' => $berth->id,
            'price' => 900,
            'is_booked' => true,
        ]);

        // 4. Delete berth
        $delRes = $this->actingAs($this->adminUser)->deleteJson("/api/admin/berths/{$berth->id}");
        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('seat_layouts', ['id' => $berth->id]);
    }

    public function test_driver_can_list_vehicle_and_pending_approval_by_admin(): void
    {
        // 1. Driver submits a new vehicle
        $payload = [
            'vehicle_model' => 'Toyota Rumion Hybrid',
            'vehicle_plate' => 'MH 12 ZZ 9999',
            'fleet_category' => 'suv',
            'seating_capacity' => 7,
            'berth_capacity' => 0,
            'fuel_type' => 'CNG',
            'model_year' => '2024',
            'rc_number' => 'RC-MH12-999999',
        ];

        $driverRes = $this->actingAs($this->driverUser)->postJson('/api/driver/vehicles', $payload);
        $driverRes->assertStatus(200);
        $driverRes->assertJson([
            'success' => true,
            'vehicle' => [
                'vehicle_plate' => 'MH 12 ZZ 9999',
                'status' => 'Pending',
                'is_active' => false,
            ]
        ]);

        $this->assertDatabaseHas('driver_vehicles', [
            'vehicle_plate' => 'MH 12 ZZ 9999',
            'status' => 'Pending',
            'is_active' => false,
        ]);

        $veh = DriverVehicle::where('vehicle_plate', 'MH 12 ZZ 9999')->first();

        // 2. Admin views vehicles
        $adminRes = $this->actingAs($this->adminUser)->getJson('/api/admin/vehicles?status=Pending');
        $adminRes->assertStatus(200);
        $adminRes->assertJsonFragment(['vehicle_plate' => 'MH 12 ZZ 9999']);

        // 3. Admin approves vehicle
        $approveRes = $this->actingAs($this->adminUser)->postJson("/api/admin/vehicles/{$veh->id}/approve");
        $approveRes->assertStatus(200);

        $veh->refresh();
        $this->assertEquals('Approved', $veh->status);
        $this->assertTrue($veh->is_active);
    }

    public function test_admin_can_reject_and_edit_vehicle(): void
    {
        $veh = DriverVehicle::where('vehicle_plate', 'MH 12 TS 9901')->first();

        // 1. Admin rejects vehicle
        $rejectRes = $this->actingAs($this->adminUser)->postJson("/api/admin/vehicles/{$veh->id}/reject", [
            'admin_notes' => 'Commercial permit expired.'
        ]);
        $rejectRes->assertStatus(200);

        $veh->refresh();
        $this->assertEquals('Rejected', $veh->status);
        $this->assertFalse($veh->is_active);

        // 2. Admin edits vehicle
        $editRes = $this->actingAs($this->adminUser)->putJson("/api/admin/vehicles/{$veh->id}", [
            'driver_name' => 'Suniel Jadhav',
            'driver_phone' => '+91 98225 33412',
            'vehicle_model' => 'Toyota Fortuner GR Sport',
            'vehicle_plate' => 'MH 12 TS 9901',
            'fleet_category' => 'luxury',
            'seating_capacity' => 7,
            'berth_capacity' => 0,
            'fuel_type' => 'Diesel',
            'model_year' => '2024',
            'status' => 'Approved',
            'is_active' => true,
        ]);
        $editRes->assertStatus(200);
        $this->assertDatabaseHas('driver_vehicles', [
            'id' => $veh->id,
            'vehicle_model' => 'Toyota Fortuner GR Sport',
            'status' => 'Approved',
            'is_active' => true,
        ]);

        // 3. Admin deletes vehicle
        $delRes = $this->actingAs($this->adminUser)->deleteJson("/api/admin/vehicles/{$veh->id}");
        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('driver_vehicles', ['id' => $veh->id]);
    }

    public function test_admin_panel_renders_new_setup_sections_and_links(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/');
        $response->assertStatus(200);

        // Check Navigation Links
        $response->assertSee('data-section="locations"', false);
        $response->assertSee('data-section="berth-routes"', false);
        $response->assertSee('data-section="berth-layouts"', false);
        $response->assertSee('data-section="vehicles"', false);

        // Check Admin Sections
        $response->assertSee('id="admin-sec-locations"', false);
        $response->assertSee('id="admin-sec-berth-routes"', false);
        $response->assertSee('id="admin-sec-berth-layouts"', false);
        $response->assertSee('id="admin-sec-vehicles"', false);

        // Check Modals
        $response->assertSee('id="admin-location-modal"', false);
        $response->assertSee('id="admin-berth-route-modal"', false);
        $response->assertSee('id="admin-berth-modal"', false);
        $response->assertSee('id="admin-vehicle-modal"', false);
        $response->assertSee('id="driver-list-vehicle-modal"', false);
    }
}

