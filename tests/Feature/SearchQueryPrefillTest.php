<?php

namespace Tests\Feature;

use App\Models\AirportHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchQueryPrefillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        AirportHub::firstOrCreate(
            ['iata_code' => 'CCU'],
            [
                'city' => 'Kolkata',
                'airport_name' => 'Netaji Subhash Chandra Bose Intl',
                'terminals' => 'T1, T2',
                'sedan_base_fare' => 750,
                'suv_base_fare' => 1100,
                'pickup_surcharge' => 100,
                'drop_surcharge' => 50,
                'is_active' => true,
            ]
        );
    }

    public function test_local_city_ride_search_query_prefills_local_tab_and_city(): void
    {
        $res = $this->get('/?pickup=murshidabad&drop=kolkata&type=Local+City+Ride');
        $res->assertOk();

        $html = $res->getContent();
        $this->assertMatchesRegularExpression('/data-type="local"[^>]*class="rental-type-tab active"|class="rental-type-tab active"[^>]*data-type="local"/', $html);
        $this->assertMatchesRegularExpression('/id="rental-form-local"[^>]*style="display:block;"/', $html);
        $this->assertMatchesRegularExpression('/id="rental-local-city"[^>]*value="Murshidabad"/', $html);
    }

    public function test_intercity_outstation_search_query_prefills_oneway_tab_and_pickup_drop(): void
    {
        $res = $this->get('/?pickup=murshidabad&drop=kolkata&type=Intercity+%2F+Outstation');
        $res->assertOk();

        $html = $res->getContent();
        $this->assertMatchesRegularExpression('/class="rental-type-tab active"\s+data-type="oneway"/', $html);
        $this->assertMatchesRegularExpression('/id="rental-form-oneway"[^>]*style="display:block;"/', $html);
        $this->assertMatchesRegularExpression('/id="rental-from-city"[^>]*value="Murshidabad"/', $html);
        $this->assertMatchesRegularExpression('/id="rental-to-city"[^>]*value="Kolkata"/', $html);
        $this->assertMatchesRegularExpression('/id="cab-pickup-address"[^>]*value="Murshidabad"/', $html);
        $this->assertMatchesRegularExpression('/id="cab-drop-address"[^>]*value="Kolkata"/', $html);
        $this->assertStringContainsString('Murshidabad → Kolkata', $html);
    }

    public function test_airport_station_transfer_search_query_prefills_airport_tab_and_direction(): void
    {
        $res = $this->get('/?pickup=murshidabad&drop=kolkata&type=Airport+%2F+Station+Transfer');
        $res->assertOk();

        $html = $res->getContent();
        $this->assertMatchesRegularExpression('/class="rental-type-tab active"\s+data-type="airport"/', $html);
        $this->assertMatchesRegularExpression('/id="rental-form-airport"[^>]*style="display:block;"/', $html);
        $this->assertMatchesRegularExpression('/id="rental-airport-address"[^>]*value="Murshidabad"/', $html);
        $this->assertMatchesRegularExpression('/id="rental-airport-name"[^>]*value="[^"]*Kolkata[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/name="airport_direction"\s+value="drop"\s+checked/', $html);
    }

    public function test_round_trip_search_query_prefills_roundtrip_tab_and_routes(): void
    {
        $res = $this->get('/?pickup=murshidabad&drop=kolkata&type=Round+Trip');
        $res->assertOk();

        $html = $res->getContent();
        $this->assertMatchesRegularExpression('/class="rental-type-tab active"\s+data-type="roundtrip"/', $html);
        $this->assertMatchesRegularExpression('/id="rental-form-roundtrip"[^>]*style="display:block;"/', $html);
        $this->assertMatchesRegularExpression('/id="rental-rt-from"[^>]*value="Murshidabad"/', $html);
        $this->assertMatchesRegularExpression('/id="rental-rt-to"[^>]*value="Kolkata"/', $html);
    }

    public function test_search_query_opens_cabs_tab_even_for_admin_while_bare_root_stays_admin(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();

        $bare = $this->actingAs($admin)->get('/');
        $bare->assertOk();
        $this->assertMatchesRegularExpression('/<section id="tab-admin" class="tab-pane active"/', $bare->getContent());

        $withQuery = $this->actingAs($admin)->get('/?pickup=murshidabad&drop=kolkata&type=Round+Trip');
        $withQuery->assertOk();
        $this->assertMatchesRegularExpression('/<section id="tab-cabs" class="tab-pane active"/', $withQuery->getContent());
    }
}
