<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\ServiceLocation;
use App\Models\RouteFare;
use App\Models\CarType;

class RegionalLocationAndDighaBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic test locations
        ServiceLocation::create([
            'name' => 'Berhampore',
            'state' => 'West Bengal (Murshidabad)',
            'latitude' => 24.0988,
            'longitude' => 88.2514,
            'is_active' => true,
        ]);

        ServiceLocation::create([
            'name' => 'Baharampur',
            'state' => 'West Bengal (Murshidabad)',
            'latitude' => 24.0988,
            'longitude' => 88.2514,
            'is_active' => true,
        ]);

        ServiceLocation::create([
            'name' => 'Digha',
            'state' => 'West Bengal (Purba Medinipur)',
            'latitude' => 21.6266,
            'longitude' => 87.5074,
            'is_active' => true,
        ]);

        // Seed Sedan and SUV car types
        CarType::create([
            'category_key' => 'sedan',
            'name' => 'Comfort Sedan',
            'models' => 'Dzire, Etios',
            'badge' => 'POPULAR',
            'seats' => '4 Seats',
            'luggage' => '2 Bags',
            'rate_per_km' => 12.5,
            'extra_km_rate' => 12.5,
            'base_fare_min' => 950,
            'order_index' => 1,
            'is_active' => true,
        ]);

        CarType::create([
            'category_key' => 'suv',
            'name' => 'Family SUV',
            'models' => 'Ertiga, Triber',
            'badge' => 'FAMILY CHOICE',
            'seats' => '6 Seats',
            'luggage' => '3 Bags',
            'rate_per_km' => 18.0,
            'extra_km_rate' => 18.0,
            'base_fare_min' => 1400,
            'order_index' => 2,
            'is_active' => true,
        ]);

        // Seed RouteFare between Berhampore and Digha
        RouteFare::create([
            'from_city' => 'Berhampore',
            'to_city' => 'Digha',
            'from_lat' => 24.0988,
            'from_lng' => 88.2514,
            'to_lat' => 21.6266,
            'to_lng' => 87.5074,
            'distance_km' => 345,
            'estimated_time' => '7h 30m',
            'base_toll' => 320,
            'is_active' => true,
        ]);
    }

    public function test_location_search_returns_registered_and_regional_desired_locations()
    {
        // 1. Search for Digha
        $response = $this->getJson('/api/locations/search?q=digha');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'query' => 'digha',
            ]);

        $locations = $response->json('locations');
        $names = array_column($locations, 'name');
        $this->assertContains('Digha', $names);

        // 2. Search for Berhampore
        $response2 = $this->getJson('/api/locations/search?q=berhampore');
        $response2->assertStatus(200);
        $names2 = array_column($response2->json('locations'), 'name');
        $this->assertContains('Berhampore', $names2);

        // 3. Search for Mandarmani (regional destination in catalog)
        $response3 = $this->getJson('/api/locations/search?q=mandarmani');
        $response3->assertStatus(200);
        $names3 = array_column($response3->json('locations'), 'name');
        $this->assertContains('Mandarmani', $names3);

        // 4. Search for custom desired location
        $response4 = $this->getJson('/api/locations/search?q=Tajpur Beach');
        $response4->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response4->json('count'));
    }

    public function test_route_calculation_for_berhampore_to_digha()
    {
        $response = $this->getJson('/api/routes/calculate?from=Berhampore&to=Digha');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'route' => [
                    'from_city' => 'Berhampore',
                    'to_city' => 'Digha',
                    'distance_km' => 345,
                    'estimated_time' => '7h 30m',
                    'base_toll' => 320,
                ],
            ]);

        $this->assertEquals(4633, $response->json('fleet_estimates.sedan'));
    }

    public function test_customer_can_book_cab_from_berhampore_to_digha()
    {
        $payload = [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Berhampore',
            'to_city' => 'Digha',
            'pickup_location' => 'Berhampore Central Bus Stand',
            'drop_location' => 'New Digha Sea Beach Road',
            'customer_name' => 'Abhishek Roy',
            'customer_phone' => '+91 98311 22334',
            'customer_email' => 'abhishek@example.com',
            'fleet_category' => 'sedan',
            'include_tolls' => true,
        ];

        $response = $this->postJson('/api/bookings', $payload);
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Trip booked successfully!',
                'booking' => [
                    'route_summary' => 'Berhampore → Digha',
                    'status' => 'Confirmed',
                    'base_fare' => 4313,
                    'toll_fare' => 320,
                    'total_amount' => 4633,
                ],
            ]);

        $this->assertDatabaseHas('bookings', [
            'route_summary' => 'Berhampore → Digha',
            'customer_name' => 'Abhishek Roy',
            'total_amount' => 4633,
        ]);
    }

    public function test_customer_can_book_cab_to_regional_desired_location()
    {
        $payload = [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Berhampore',
            'to_city' => 'Mandarmani',
            'pickup_location' => 'Berhampore Station Area',
            'drop_location' => 'Mandarmani Sea View Resort',
            'customer_name' => 'Tania Sarkar',
            'customer_phone' => '+91 98311 99887',
            'customer_email' => 'tania@example.com',
            'fleet_category' => 'suv',
            'include_tolls' => true,
        ];

        $response = $this->postJson('/api/bookings', $payload);
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'booking' => [
                    'route_summary' => 'Berhampore → Mandarmani',
                    'status' => 'Confirmed',
                ],
            ]);

        // Asserts total amount calculated with dynamic distance (~337 km)
        $booking = $response->json('booking');
        $this->assertGreaterThan(5000, $booking['total_amount']);
    }
}
