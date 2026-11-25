<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverVehicle;
use App\Models\User;
use Database\Seeders\FleetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetScaleOutSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FleetSeeder::class);
    }

    public function test_it_seeds_one_hundred_drivers_and_five_hundred_cars(): void
    {
        $this->assertSame(FleetSeeder::DRIVER_COUNT, Driver::count());
        $this->assertSame(FleetSeeder::VEHICLE_COUNT, DriverVehicle::count());

        $this->assertSame(500, DriverVehicle::whereNotNull('driver_id')->count());
        $this->assertSame(500, DriverVehicle::whereNotNull('features')->count());
    }

    public function test_every_car_has_a_catalogue_feature_list(): void
    {
        $catalogue = DriverVehicle::FEATURES;

        foreach (DriverVehicle::all() as $vehicle) {
            $features = $vehicle->featureLabels();

            $this->assertNotEmpty($features, "Car {$vehicle->vehicle_plate} has no features.");
            $this->assertContains('Air Conditioning', $features, "Car {$vehicle->vehicle_plate} lacks AC.");
            $this->assertLessThanOrEqual(count($catalogue), count($features));

            foreach ($features as $feature) {
                $this->assertContains($feature, $catalogue, "Unknown feature label '{$feature}'.");
            }

            $this->assertContains(
                $vehicle->fleet_category,
                ['sedan', 'prime', 'suv', 'luxury', 'sleeper_van']
            );
        }
    }

    public function test_every_driver_owns_five_cars(): void
    {
        $garages = DriverVehicle::selectRaw('driver_id, COUNT(*) as cars')
            ->whereNotNull('driver_id')
            ->groupBy('driver_id')
            ->pluck('cars', 'driver_id');

        $this->assertCount(FleetSeeder::DRIVER_COUNT, $garages);
        $this->assertSame([5], $garages->unique()->values()->all());
    }

    public function test_reseeding_does_not_duplicate_the_fleet(): void
    {
        $this->seed(FleetSeeder::class);
        $this->seed(FleetSeeder::class);

        $this->assertSame(FleetSeeder::DRIVER_COUNT, Driver::count());
        $this->assertSame(FleetSeeder::VEHICLE_COUNT, DriverVehicle::count());
    }

    public function test_admin_can_save_car_features_and_unknown_labels_are_dropped(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = DriverVehicle::firstOrFail();

        $response = $this->actingAs($admin)->putJson("/api/admin/vehicles/{$vehicle->id}", [
            'driver_name' => $vehicle->driver_name,
            'driver_phone' => $vehicle->driver_phone,
            'vehicle_model' => $vehicle->vehicle_model,
            'vehicle_plate' => $vehicle->vehicle_plate,
            'fleet_category' => $vehicle->fleet_category,
            'seating_capacity' => $vehicle->seating_capacity,
            'berth_capacity' => $vehicle->berth_capacity,
            'fuel_type' => $vehicle->fuel_type,
            'model_year' => $vehicle->model_year,
            'rc_number' => $vehicle->rc_number,
            'features' => ['Air Conditioning', 'Dash Cam', 'Espresso Machine'],
            'status' => 'Approved',
            'is_active' => true,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertSame(
            ['Air Conditioning', 'Dash Cam'],
            $vehicle->fresh()->featureLabels(),
            'Only catalogue amenities should persist.'
        );
    }

    public function test_reseeding_backfills_features_for_older_cars(): void
    {
        $legacy = DriverVehicle::create([
            'driver_name' => 'Legacy Partner',
            'driver_phone' => '+91 90000 99999',
            'vehicle_model' => 'Toyota Qualis',
            'vehicle_plate' => 'MH 01 OLD 0001',
            'fleet_category' => 'suv',
            'seating_capacity' => 7,
            'fuel_type' => 'Diesel',
            'model_year' => '2012',
            'status' => 'Approved',
            'features' => null,
            'is_active' => true,
        ]);

        $this->seed(FleetSeeder::class);

        $this->assertContains('Air Conditioning', $legacy->fresh()->featureLabels());
    }

    public function test_admin_fleet_page_renders_amenity_chips(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = DriverVehicle::where('status', 'Approved')->firstOrFail();
        $feature = $vehicle->featureLabels()[0];

        $response = $this->actingAs($admin)->get('/');

        $response->assertOk();
        $response->assertSee('Car Features');
        $response->assertSee($vehicle->vehicle_plate);
        $response->assertSee($feature);
    }

    public function test_driver_can_list_a_car_with_features(): void
    {
        $driver = Driver::firstOrFail();
        $account = User::factory()->create([
            'role' => 'driver',
            'name' => $driver->name,
            'phone' => $driver->phone,
        ]);

        $response = $this->actingAs($account)->postJson('/api/driver/vehicles', [
            'vehicle_model' => 'Toyota Innova Hycross',
            'vehicle_plate' => 'MH 99 ZZ 4321',
            'fleet_category' => 'suv',
            'seating_capacity' => 7,
            'berth_capacity' => 0,
            'fuel_type' => 'Petrol',
            'model_year' => '2025',
            'rc_number' => 'RC-MH99-432198',
            'features' => ['Air Conditioning', 'Child Seat', 'Wi-Fi Hotspot'],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $listed = DriverVehicle::where('vehicle_plate', 'MH 99 ZZ 4321')->firstOrFail();

        $this->assertSame('Pending', $listed->status);
        $this->assertSame($driver->id, $listed->driver_id);
        $this->assertSame(
            ['Air Conditioning', 'Child Seat', 'Wi-Fi Hotspot'],
            $listed->featureLabels()
        );
    }
}
