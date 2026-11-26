<?php

namespace Tests\Feature;

use App\Models\BiddingSession;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverBid;
use App\Models\PlatformSetting;
use App\Models\RouteFare;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The driver marketplace: admin-published auction, descending bids, masked
 * information until approval, and the winning bid (not a fare percentage) paid
 * to the driver.
 */
class DriverBiddingMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $driverUser;

    protected Driver $driver;

    protected User $otherDriverUser;

    protected Driver $otherDriver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        PlatformSetting::set('enable_schedules_and_berths', '1');

        $this->admin = User::where('role', 'admin')->firstOrFail();

        $this->driverUser = User::where('role', 'driver')->firstOrFail();
        $this->driver = Driver::where('phone', $this->driverUser->phone)->firstOrFail();

        $this->otherDriverUser = User::create([
            'name' => 'Second Chauffeur',
            'email' => 'second.driver@example.com',
            'phone' => '+91 90000 54321',
            'password' => bcrypt('password123'),
            'role' => 'driver',
        ]);

        $this->otherDriver = Driver::create([
            'name' => 'Second Chauffeur',
            'phone' => '+91 90000 54321',
            'vehicle_model' => 'Maruti Ertiga',
            'vehicle_plate' => 'WB 02 ZZ 4321',
            'fleet_category' => 'suv',
            'rating' => 4.7,
            'is_verified' => true,
            'is_online' => true,
        ]);
    }

    /**
     * A confirmed cab booking worth ₹8,580 (450 km SUV @ ₹18/km + ₹480 toll).
     */
    private function booking(): Booking
    {
        $response = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Baner, Pune',
            'drop_location' => 'Panjim, Goa',
            'customer_name' => 'Priya Menon',
            'customer_phone' => '+91 98888 11111',
            'customer_email' => 'priya@example.com',
            'fleet_category' => 'suv',
            'include_tolls' => true,
        ])->assertStatus(200);

        return Booking::where('booking_reference', $response->json('booking.booking_reference'))->firstOrFail();
    }

    private function openSession(Booking $booking, array $overrides = []): BiddingSession
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$booking->id}/bidding", array_merge([
                'start_amount' => 4000,
                'admin_fee_pct' => 15,
                'closes_at' => now()->addHours(4)->toDateTimeString(),
            ], $overrides));

        $response->assertStatus(201);

        return BiddingSession::findOrFail($response->json('session.id'));
    }

    /**
     * Drop the authenticated identity so a request is genuinely anonymous.
     * `actingAs()` otherwise persists for the rest of the test.
     */
    private function asGuest(): void
    {
        auth()->logout();
        $this->app['auth']->forgetGuards();
    }

    /**
     * Bid from a distinct IP so the shared-IP manipulation guard does not trip.
     */
    private function fromIp(string $ip): self
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        return $this;
    }

    // ------------------------------------------------------------ opening

    public function test_admin_can_open_bidding_and_receives_a_whatsapp_share_package(): void
    {
        $booking = $this->booking();
        $session = $this->openSession($booking);

        $this->assertSame(BiddingSession::STATUS_OPEN, $session->status);
        $this->assertSame(48, strlen($session->token));
        $this->assertSame('Bidding', $booking->fresh()->status);

        $response = $this->actingAs($this->admin)->getJson("/api/admin/biddings/{$session->id}");
        $response->assertStatus(200)
            ->assertJsonPath('session.start_amount', 4000)
            ->assertJsonPath('session.admin_fee_pct', 15)
            ->assertJsonStructure(['share' => ['url', 'whatsapp_url', 'text']]);

        $text = $response->json('share.text');
        $this->assertStringContainsString('Pune', $text);
        $this->assertStringContainsString('Goa', $text);
        $this->assertStringContainsString($response->json('share.url'), $text);

        // The share message must not carry customer identity.
        $this->assertStringNotContainsString('Priya', $text);
        $this->assertStringNotContainsString('98888', $text);
        $this->assertStringContainsString('wa.me', $response->json('share.whatsapp_url'));
    }

    public function test_start_price_cannot_exceed_the_fare_minus_the_admin_margin(): void
    {
        $booking = $this->booking();
        $fare = (float) $booking->total_amount;

        // 15% margin -> max payable to a driver is 85% of the fare.
        $max = floor($fare * 0.85);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$booking->id}/bidding", [
                'start_amount' => $max + 500,
                'admin_fee_pct' => 15,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_start_amount');

        $session = $this->openSession($booking);

        $this->assertEquals($max, $session->maxDriverPrice());
        $this->assertSame('Bidding', $booking->fresh()->status);
    }

    public function test_a_booking_cannot_have_two_open_auctions(): void
    {
        $booking = $this->booking();
        $this->openSession($booking);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$booking->id}/bidding", ['start_amount' => 3000])
            ->assertStatus(409)
            ->assertJsonPath('code', 'bidding_already_open');
    }

    public function test_an_assigned_booking_cannot_be_reopened_for_bidding(): void
    {
        $booking = $this->booking();
        $booking->forceFill([
            'status' => 'Driver Assigned',
            'assigned_driver_id' => $this->driver->id,
        ])->save();

        $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$booking->id}/bidding", ['start_amount' => 3000])
            ->assertStatus(422)
            ->assertJsonPath('code', 'booking_not_biddable');
    }

    // ------------------------------------------------------- masking

    public function test_driver_payload_hides_customer_identity_contact_addresses_and_fare(): void
    {
        $booking = $this->booking();
        $session = $this->openSession($booking);

        $response = $this->getJson("/api/bidding/{$session->token}")->assertStatus(200);

        $payload = $response->json('session');

        // Allowed
        $this->assertSame($booking->booking_reference, $payload['booking_reference']);
        $this->assertSame('Pune', $payload['pickup_city']);
        $this->assertSame('Goa', $payload['drop_city']);
        $this->assertEquals(4000, $payload['start_amount']);
        $this->assertNull($payload['current_lowest']);

        // Forbidden, by key
        foreach ([
            'customer_name', 'customer_phone', 'customer_email',
            'pickup_location', 'drop_location',
            'total_amount', 'admin_fee_pct', 'base_fare',
        ] as $key) {
            $this->assertArrayNotHasKey($key, $payload, "Leaked {$key} to a bidding driver");
        }

        // Forbidden, by value (defence in depth: the whole body must not contain them)
        $body = $response->getContent();
        $this->assertStringNotContainsString('Priya Menon', $body);
        $this->assertStringNotContainsString('98888 11111', $body);
        $this->assertStringNotContainsString('Baner', $body);
        $this->assertStringNotContainsString((string) $booking->total_amount, $body);
    }

    public function test_bidding_page_shows_only_the_route_to_an_anonymous_visitor(): void
    {
        $booking = $this->booking();
        $session = $this->openSession($booking);

        $response = $this->get("/bid/{$session->token}");

        $response->assertStatus(200)
            ->assertSee('Driver Bidding')
            ->assertSee('Pune')
            ->assertSee('Goa')
            ->assertSee('4,000')
            ->assertDontSee('Priya Menon')
            ->assertDontSee('98888 11111')
            ->assertDontSee('Baner');
    }

    // ------------------------------------------------------- bidding

    public function test_guest_cannot_bid_and_customer_cannot_bid(): void
    {
        $session = $this->openSession($this->booking());

        $this->asGuest();

        $this->postJson("/api/bidding/{$session->token}/bids", ['amount' => 3900])
            ->assertStatus(401);

        $customer = User::where('role', 'customer')->firstOrFail();

        $this->actingAs($customer)
            ->postJson("/api/bidding/{$session->token}/bids", ['amount' => 3900])
            ->assertStatus(403);
    }

    public function test_drivers_compete_downwards_and_only_the_lowest_pays(): void
    {
        $session = $this->openSession($this->booking());
        $url = "/api/bidding/{$session->token}/bids";

        $this->fromIp('10.10.0.1')->actingAs($this->driverUser)
            ->postJson($url, ['amount' => 3800])
            ->assertStatus(200)
            ->assertJsonPath('bid.amount', 3800);

        $this->fromIp('10.10.0.2')->actingAs($this->otherDriverUser)
            ->postJson($url, ['amount' => 3500])
            ->assertStatus(200);

        // Above the current lowest -> rejected.
        $this->fromIp('10.10.0.1')->actingAs($this->driverUser)
            ->postJson($url, ['amount' => 3600])
            ->assertStatus(422)
            ->assertJsonPath('code', 'bid_not_low_enough');

        // Equal to the current lowest -> also rejected (must strictly undercut).
        $this->fromIp('10.10.0.1')->actingAs($this->driverUser)
            ->postJson($url, ['amount' => 3500])
            ->assertStatus(422)
            ->assertJsonPath('code', 'bid_not_low_enough');

        $this->assertEquals(3500, $session->fresh()->currentLowestAmount());
        $this->assertSame(2, DriverBid::where('session_id', $session->id)->count());
    }

    public function test_driver_sees_only_the_lowest_price_and_not_competitor_identities(): void
    {
        $session = $this->openSession($this->booking());
        $url = "/api/bidding/{$session->token}/bids";

        $this->actingAs($this->driverUser)->postJson($url, ['amount' => 3800])->assertStatus(200);

        $view = $this->actingAs($this->otherDriverUser)
            ->getJson("/api/bidding/{$session->token}")
            ->assertStatus(200);

        $this->assertEquals(3800, $view->json('session.current_lowest'));

        // No competitor identity anywhere in the payload.
        $body = $view->getContent();
        $this->assertStringNotContainsString($this->driver->name, $body);
        $this->assertStringNotContainsString($this->driver->phone, $body);

        // And no per-driver bid history.
        $this->assertArrayNotHasKey('bids', $view->json('session'));
    }

    public function test_unverified_driver_cannot_bid(): void
    {
        $session = $this->openSession($this->booking());

        $this->driver->forceFill(['is_verified' => false])->save();

        $this->actingAs($this->driverUser)
            ->postJson("/api/bidding/{$session->token}/bids", ['amount' => 3800])
            ->assertStatus(403)
            ->assertJsonPath('code', 'driver_not_eligible');
    }

    public function test_bidding_closes_at_the_deadline(): void
    {
        $booking = $this->booking();
        $session = $this->openSession($booking, ['closes_at' => now()->addMinutes(10)->toDateTimeString()]);

        $this->travel(11)->minutes();

        // Reading the link lazily closes the expired auction.
        $this->getJson("/api/bidding/{$session->token}")
            ->assertStatus(200)
            ->assertJsonPath('session.status', 'closed')
            ->assertJsonPath('session.is_open', false);

        $this->actingAs($this->driverUser)
            ->postJson("/api/bidding/{$session->token}/bids", ['amount' => 3000])
            ->assertStatus(409)
            ->assertJsonPath('code', 'bidding_closed');
    }

    public function test_admin_can_close_bidding_manually(): void
    {
        $session = $this->openSession($this->booking());

        $this->actingAs($this->admin)
            ->postJson("/api/admin/biddings/{$session->id}/close")
            ->assertStatus(200)
            ->assertJsonPath('session.status', 'closed');

        $this->actingAs($this->driverUser)
            ->postJson("/api/bidding/{$session->token}/bids", ['amount' => 3900])
            ->assertStatus(409);
    }

    // ------------------------------------------------------- award

    public function test_admin_approval_assigns_the_lowest_bidder_and_reveals_details(): void
    {
        $booking = $this->booking();
        $session = $this->openSession($booking);
        $url = "/api/bidding/{$session->token}/bids";

        $this->fromIp('10.10.1.1')->actingAs($this->driverUser)->postJson($url, ['amount' => 3800])->assertStatus(200);
        $this->fromIp('10.10.1.2')->actingAs($this->otherDriverUser)->postJson($url, ['amount' => 3200])->assertStatus(200);

        $award = $this->actingAs($this->admin)
            ->postJson("/api/admin/biddings/{$session->id}/award")
            ->assertStatus(200)
            ->assertJsonPath('booking.status', 'Driver Assigned')
            ->assertJsonPath('booking.assigned_driver_id', $this->otherDriver->id)
            ->assertJsonPath('driver.vehicle_plate', 'WB 02 ZZ 4321');

        $this->assertEquals(3200.0, (float) $award->json('booking.driver_payout_amount'));

        // Admin keeps the spread between the customer fare and the winning bid.
        $expectedProfit = round((float) $booking->total_amount - 3200, 2);
        $this->assertEquals($expectedProfit, (float) $award->json('booking.admin_profit'));

        $this->assertSame(BiddingSession::STATUS_AWARDED, $session->fresh()->status);
        $this->assertNotNull($session->fresh()->awarded_at);

        // Winner's page now carries the full trip details...
        $this->actingAs($this->otherDriverUser)
            ->get("/bid/{$session->token}")
            ->assertStatus(200)
            ->assertSee('Full trip details released')
            ->assertSee('Priya Menon')
            ->assertSee('+91 98888 11111');

        // ...and the losing driver's page does not.
        $this->actingAs($this->driverUser)
            ->get("/bid/{$session->token}")
            ->assertStatus(200)
            ->assertDontSee('Priya Menon')
            ->assertDontSee('+91 98888 11111');
    }

    public function test_awarding_without_bids_is_refused(): void
    {
        $session = $this->openSession($this->booking());

        $this->actingAs($this->admin)
            ->postJson("/api/admin/biddings/{$session->id}/award")
            ->assertStatus(422)
            ->assertJsonPath('code', 'no_bids');
    }

    public function test_awarding_twice_is_refused(): void
    {
        $session = $this->openSession($this->booking());
        $this->actingAs($this->driverUser)
            ->postJson("/api/bidding/{$session->token}/bids", ['amount' => 3800])->assertStatus(200);

        $this->actingAs($this->admin)->postJson("/api/admin/biddings/{$session->id}/award")->assertStatus(200);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/biddings/{$session->id}/award")
            ->assertStatus(409)
            ->assertJsonPath('code', 'bidding_already_awarded');
    }

    public function test_cancelling_returns_the_booking_to_the_confirmed_pool(): void
    {
        $booking = $this->booking();
        $session = $this->openSession($booking);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/biddings/{$session->id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('session.status', 'cancelled');

        $this->assertSame('Confirmed', $booking->fresh()->status);
    }

    public function test_a_flagged_bid_from_a_second_account_on_the_same_ip_cannot_win(): void
    {
        $session = $this->openSession($this->booking());
        $url = "/api/bidding/{$session->token}/bids";

        // Both bids arrive from the default test IP.
        $this->actingAs($this->driverUser)->postJson($url, ['amount' => 3800])->assertStatus(200);

        $this->actingAs($this->otherDriverUser)
            ->postJson($url, ['amount' => 3000])
            ->assertStatus(200);

        $flagged = DriverBid::where('session_id', $session->id)->where('flagged', true)->first();
        $this->assertNotNull($flagged, 'The second account on the same IP should be flagged.');
        $this->assertSame($this->otherDriver->id, $flagged->driver_id);

        // The flagged bid is excluded from the provisional win.
        $this->assertEquals(3800, $session->fresh()->currentLowestAmount());

        $admin = $this->actingAs($this->admin)->getJson("/api/admin/biddings/{$session->id}");
        $this->assertTrue(collect($admin->json('session.bids'))->contains(fn ($b) => $b['flagged'] === true));
    }

    public function test_winner_is_paid_their_bid_not_a_percentage_of_the_fare(): void
    {
        $booking = $this->booking();
        $session = $this->openSession($booking);

        $this->actingAs($this->otherDriverUser)
            ->postJson("/api/bidding/{$session->token}/bids", ['amount' => 3200])
            ->assertStatus(200);

        $this->actingAs($this->admin)->postJson("/api/admin/biddings/{$session->id}/award")->assertStatus(200);

        $booking->refresh();
        $walletBefore = (float) $this->otherDriver->fresh()->wallet_balance;
        $otp = $booking->start_otp;

        $this->actingAs($this->otherDriverUser)
            ->postJson("/api/driver/trips/{$booking->id}/verify-otp", ['otp' => $otp])
            ->assertStatus(200);

        $this->actingAs($this->otherDriverUser)
            ->postJson("/api/driver/trips/{$booking->id}/complete")
            ->assertStatus(200);

        $this->assertEquals($walletBefore + 3200.0, (float) $this->otherDriver->fresh()->wallet_balance);

        $transaction = WalletTransaction::where('booking_id', $booking->id)->firstOrFail();
        $this->assertEquals(3200.0, (float) $transaction->amount);
    }

    // ------------------------------------------------------- authorisation

    public function test_bidding_admin_endpoints_are_admin_only(): void
    {
        $session = $this->openSession($this->booking());
        $customer = User::where('role', 'customer')->firstOrFail();

        foreach ([
            ['get', '/api/admin/biddings'],
            ['get', "/api/admin/biddings/{$session->id}"],
            ['post', "/api/admin/biddings/{$session->id}/close"],
            ['post', "/api/admin/biddings/{$session->id}/award"],
            ['post', "/api/admin/biddings/{$session->id}/cancel"],
        ] as [$verb, $uri]) {
            // actingAs() persists, so reset before every anonymous attempt.
            $this->asGuest();
            $this->{$verb.'Json'}($uri)->assertStatus(401);

            $this->actingAs($customer)->{$verb.'Json'}($uri)->assertStatus(403);
            $this->actingAs($this->driverUser)->{$verb.'Json'}($uri)->assertStatus(403);
        }
    }

    public function test_unknown_link_token_returns_404_and_no_booking_data(): void
    {
        $this->getJson('/api/bidding/not-a-real-token')
            ->assertStatus(404)
            ->assertJsonPath('code', 'bidding_not_found');

        $this->get('/bid/not-a-real-token')
            ->assertStatus(404)
            ->assertSee('This bidding link is not valid');
    }

    public function test_distance_comes_from_the_route_table_not_an_invention(): void
    {
        $route = RouteFare::where('from_city', 'Pune')->where('to_city', 'Goa')->first();

        if (! $route) {
            $this->markTestSkipped('Seeded Pune-Goa route not found.');
        }

        $session = $this->openSession($this->booking());

        $this->assertSame((int) $route->distance_km, $session->distance_km);
    }

    public function test_min_and_max_service_charge_limits_instant_win_terms_and_post_win_reassignment(): void
    {
        // Admin configures 10% min service charge and 50% max service charge (auto-win floor).
        $this->actingAs($this->admin)
            ->postJson('/api/admin/settings/bidding', [
                'bidding_min_service_charge_pct' => 10,
                'bidding_max_service_charge_pct' => 50,
            ])
            ->assertStatus(200)
            ->assertJsonPath('settings.bidding_min_service_charge_pct', '10')
            ->assertJsonPath('settings.bidding_max_service_charge_pct', '50');

        // Booking of ₹5,000 auto-opens auction capped at ₹4,500 (10%) with ₹2,500 floor (50%).
        $booking = Booking::create([
            'booking_reference' => 'ICW-BID-5000',
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'route_summary' => 'Kolkata → Digha',
            'pickup_location' => 'Esplanade, Kolkata',
            'drop_location' => 'New Digha Beach',
            'customer_name' => 'Ananya Roy',
            'customer_phone' => '+91 98300 55555',
            'base_fare' => 5000,
            'total_amount' => 5000,
            'status' => 'Requested',
            'start_otp' => '4321',
        ]);

        $session = app(\App\Services\BiddingService::class)->openForNewBooking($booking);
        $this->assertNotNull($session);
        $this->assertEquals(4500, $session->start_amount);
        $this->assertEquals(2500, $session->minDriverPrice());

        $url = "/api/bidding/{$session->token}/bids";

        // Bidder must accept terms when agreed_terms is provided.
        $this->actingAs($this->driverUser)
            ->postJson($url, ['amount' => 4500, 'agreed_terms' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['agreed_terms']);

        // Bidding beyond ₹4,500 (10% min service charge cap) is refused.
        $this->actingAs($this->driverUser)
            ->postJson($url, ['amount' => 4501, 'agreed_terms' => true])
            ->assertStatus(422)
            ->assertJsonPath('code', 'bid_not_low_enough');

        // Driver 1 quotes ₹4,500, Driver 2 undercuts at ₹4,300.
        $this->fromIp('10.20.0.1')->actingAs($this->driverUser)
            ->postJson($url, ['amount' => 4500, 'agreed_terms' => true])
            ->assertStatus(200)
            ->assertJsonPath('instant_win', false);

        $this->fromIp('10.20.0.2')->actingAs($this->otherDriverUser)
            ->postJson($url, ['amount' => 4300, 'agreed_terms' => true])
            ->assertStatus(200)
            ->assertJsonPath('instant_win', false);

        // Bidding below the 50% floor (₹2,500) is rejected.
        $this->fromIp('10.20.0.1')->actingAs($this->driverUser)
            ->postJson($url, ['amount' => 2400, 'agreed_terms' => true])
            ->assertStatus(422)
            ->assertJsonPath('code', 'bid_below_minimum');

        // Reaching the 50% max discount floor (₹2,500) wins immediately and stops bidding.
        $this->fromIp('10.20.0.1')->actingAs($this->driverUser)
            ->postJson($url, ['amount' => 2500, 'agreed_terms' => true])
            ->assertStatus(200)
            ->assertJsonPath('instant_win', true)
            ->assertJsonPath('session.status', BiddingSession::STATUS_AWARDED);

        $this->assertSame('Driver Assigned', $booking->fresh()->status);
        $this->assertSame($this->driver->id, $booking->fresh()->assigned_driver_id);

        // Bidding is now stopped.
        $this->fromIp('10.20.0.2')->actingAs($this->otherDriverUser)
            ->postJson($url, ['amount' => 2500, 'agreed_terms' => true])
            ->assertStatus(409)
            ->assertJsonPath('code', 'bidding_already_awarded');

        // Admin can change the allotted driver after winning.
        $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$booking->id}/allot-driver", [
                'driver_id' => $this->otherDriver->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('booking.assigned_driver_id', $this->otherDriver->id);
    }
}
