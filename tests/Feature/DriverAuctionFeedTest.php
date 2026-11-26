<?php

namespace Tests\Feature;

use App\Models\BiddingSession;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The driver portal feed: what a signed-in driver sees in "Live Bids & Auctions",
 * and what the winner sees once operations approves the auction.
 *
 * The feed is the portal's only source of auctions. It reuses the same masked
 * payload as the WhatsApp page, so route, distance, money and deadline are
 * visible and customer identity is not.
 */
class DriverAuctionFeedTest extends TestCase
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
            'email' => 'feed.second.driver@example.com',
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

    private function booking(string $from = 'Pune', string $to = 'Goa'): Booking
    {
        $response = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => $from,
            'to_city' => $to,
            'pickup_location' => 'Baner, Pune',
            'drop_location' => 'Panjim, Goa',
            'customer_name' => 'Priya Menon',
            'customer_phone' => '+91 98888 11111',
            'customer_email' => 'priya@example.com',
            'fleet_category' => 'suv',
            'include_tolls' => true,
            'departure_time' => '2026-12-01 06:30:00',
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

    private function bid(string $token, string $ip = '10.1.0.1'): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->actingAs($this->driverUser)
            ->postJson("/api/bidding/{$token}/bids", ['amount' => 3900])
            ->assertStatus(200);
    }

    private function asGuest(): void
    {
        auth()->logout();
        $this->app['auth']->forgetGuards();
    }

    // ------------------------------------------------------------ feed

    public function test_the_portal_feed_lists_open_auctions_without_customer_identity(): void
    {
        $booking = $this->booking();
        $session = $this->openSession($booking);

        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/driver/auctions')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 1);

        $auction = $response->json('auctions.0');

        // Everything a driver needs to decide whether to bid.
        $this->assertSame($session->token, $auction['token']);
        $this->assertSame($booking->booking_reference, $auction['booking_reference']);
        $this->assertSame('Pune', $auction['pickup_city']);
        $this->assertSame('Goa', $auction['drop_city']);
        $this->assertSame(4000, (int) $auction['start_amount']);
        $this->assertNotNull($auction['distance_km']);
        $this->assertNotNull($auction['departure_at']);
        $this->assertNotNull($auction['closes_at']);
        $this->assertSame('/bid/'.$session->token, parse_url($auction['bid_url'], PHP_URL_PATH));
        $this->assertSame(0, $auction['my_bids']);
        $this->assertSame(BiddingSession::MAX_BIDS_PER_DRIVER, $auction['remaining_bids']);

        // Customer identity, exact addresses and the fare stay out of the feed.
        foreach ([
            'customer_name', 'customer_phone', 'customer_email',
            'pickup_location', 'drop_location', 'total_amount', 'admin_fee_pct',
        ] as $key) {
            $this->assertArrayNotHasKey($key, $auction, "Leaked {$key} in the portal feed");
        }

        $body = $response->getContent();
        $this->assertStringNotContainsString('Priya Menon', $body);
        $this->assertStringNotContainsString('98888 11111', $body);
        $this->assertStringNotContainsString((string) $booking->total_amount, $body);
    }

    public function test_the_feed_reports_the_drivers_own_bid_without_revealing_competitors(): void
    {
        $session = $this->openSession($this->booking());

        $this->bid($session->token, '10.1.0.1');

        $auction = $this->actingAs($this->driverUser)
            ->getJson('/api/driver/auctions')
            ->assertStatus(200)
            ->json('auctions.0');

        $this->assertEquals(3900, $auction['current_lowest']);
        $this->assertTrue($auction['you_are_lowest']);
        $this->assertTrue($auction['has_bids']);
        $this->assertSame(1, $auction['bid_count']);
        $this->assertEquals(3900, $auction['my_latest_bid']);
        $this->assertSame(1, $auction['my_bids']);
        $this->assertSame(BiddingSession::MAX_BIDS_PER_DRIVER - 1, $auction['remaining_bids']);

        // A second driver sees the price but not who holds it.
        $otherView = $this->actingAs($this->otherDriverUser)
            ->getJson('/api/driver/auctions')
            ->assertStatus(200)
            ->json('auctions.0');

        $this->assertEquals(3900, $otherView['current_lowest']);
        $this->assertFalse($otherView['you_are_lowest']);
        $this->assertTrue($otherView['has_bids']);
        $this->assertNull($otherView['my_latest_bid']);
        $this->assertSame(0, $otherView['my_bids']);
        $this->assertArrayNotHasKey('driver_name', $otherView);
    }

    public function test_only_open_auctions_are_listed(): void
    {
        $open = $this->openSession($this->booking());

        // Same fare basis for the negative cases, so only the status differs.
        $closed = $this->openSession($this->booking());
        $closed->forceFill(['status' => BiddingSession::STATUS_CLOSED, 'closed_at' => now()])->save();

        $cancelled = $this->openSession($this->booking());
        $cancelled->forceFill(['status' => BiddingSession::STATUS_CANCELLED, 'closed_at' => now()])->save();

        $expired = $this->openSession($this->booking(), [
            'closes_at' => now()->addMinutes(5)->toDateTimeString(),
        ]);

        $this->travel(10)->minutes();

        $response = $this->actingAs($this->driverUser)
            ->getJson('/api/driver/auctions')
            ->assertStatus(200);

        $tokens = collect($response->json('auctions'))->pluck('token')->all();

        $this->assertSame([$open->token], $tokens);
        $this->assertNotContains($closed->token, $tokens);
        $this->assertNotContains($cancelled->token, $tokens);
        $this->assertNotContains($expired->token, $tokens);
    }

    public function test_the_feed_is_driver_only(): void
    {
        $this->openSession($this->booking());

        $this->asGuest();
        $this->getJson('/api/driver/auctions')->assertStatus(401);

        $this->actingAs(User::where('role', 'customer')->firstOrFail())
            ->getJson('/api/driver/auctions')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden. Required role: driver|admin');
    }

    // --------------------------------------------------- portal + reveal

    public function test_the_portal_shows_the_auction_tab_and_its_refresh_action(): void
    {
        $this->openSession($this->booking());

        $this->actingAs($this->driverUser)->get('/')
            ->assertStatus(200)
            ->assertSee('Available Outstation Trips for Driver Bidding')
            ->assertSee('Pick an Open Trip')
            ->assertSee('Bid Lower to Lead')
            ->assertSee('Win &amp; Unlock', false)
            ->assertSee('id="driver-bidding-status-filters"', false)
            ->assertSee("setDriverAuctionFilter('fresh'", false)
            ->assertSee("setDriverAuctionFilter('active_bids'", false)
            ->assertSee("setDriverAuctionFilter('my_bids'", false)
            ->assertSee("setDriverAuctionFilter('allotted'", false)
            ->assertSee('refreshDriverAuctions()', false)
            ->assertSee('id="bidding-cards-stream"', false);
    }

    public function test_the_winner_sees_contact_details_and_payout_in_the_portal(): void
    {
        $booking = $this->booking();
        $session = $this->openSession($booking);

        $this->bid($session->token, '10.1.0.1');

        $this->actingAs($this->admin)
            ->postJson("/api/admin/biddings/{$session->id}/award")
            ->assertStatus(200)
            ->assertJsonPath('booking.status', 'Driver Assigned');

        $booking->refresh();

        $page = $this->actingAs($this->driverUser)->get('/')->assertStatus(200);

        // Contact details, the trip and the locked payout are on the winner's page.
        $page->assertSee('Priya Menon');
        $page->assertSee('98888 11111');
        $page->assertSee($booking->booking_reference);
        $page->assertSee('Your winning bid payout');
        $page->assertSee(number_format((float) $booking->driver_payout_amount));
        $page->assertSee('Won via driver bidding');
    }

    public function test_the_loser_does_not_get_the_trip_in_the_portal(): void
    {
        $booking = $this->booking();
        $session = $this->openSession($booking);

        $this->bid($session->token, '10.1.0.1');

        $this->actingAs($this->admin)
            ->postJson("/api/admin/biddings/{$session->id}/award")
            ->assertStatus(200);

        $this->actingAs($this->otherDriverUser)->get('/')
            ->assertStatus(200)
            ->assertDontSee('Priya Menon')
            ->assertDontSee('98888 11111')
            ->assertDontSee($booking->booking_reference);
    }

    public function test_the_winning_bidder_sees_the_reveal_on_the_bidding_page(): void
    {
        $booking = $this->booking();
        $session = $this->openSession($booking);

        $this->bid($session->token, '10.1.0.1');

        $this->actingAs($this->admin)->postJson("/api/admin/biddings/{$session->id}/award")->assertStatus(200);

        $this->actingAs($this->driverUser)->get("/bid/{$session->token}")
            ->assertStatus(200)
            ->assertSee('You won this trip')
            ->assertSee('Priya Menon')
            ->assertSee('98888 11111');
    }

    public function test_the_bidding_page_shows_the_departure_date_and_time(): void
    {
        $session = $this->openSession($this->booking());

        $this->get("/bid/{$session->token}")
            ->assertStatus(200)
            ->assertSee('01 Dec 2026, 06:30 AM');
    }

    public function test_admin_has_full_access_to_driver_auctions_and_bidding(): void
    {
        $session = $this->openSession($this->booking());

        $this->actingAs($this->admin)->get('/')
            ->assertStatus(200)
            ->assertSee('data-section="bidding"', false)
            ->assertSee('id="admin-sec-bidding"', false)
            ->assertSee('openBiddingForBooking(', false)
            ->assertSee('id="admin-bidding-open-modal"', false)
            ->assertSee('id="admin-bidding-detail-modal"', false)
            ->assertSee('id="bidding-cards-stream"', false);

        $this->actingAs($this->admin)
            ->getJson('/api/driver/auctions')
            ->assertStatus(200)
            ->assertJsonPath('can_bid', true)
            ->assertJsonPath('is_admin', true)
            ->assertJsonPath('count', 1);

        $this->actingAs($this->admin)
            ->get("/bid/{$session->token}")
            ->assertStatus(200)
            ->assertSee('Place your bid')
            ->assertDontSee('pending verification');

        $this->actingAs($this->admin)
            ->postJson("/api/bidding/{$session->token}/bids", ['amount' => 3850])
            ->assertStatus(200)
            ->assertJsonPath('bid.amount', 3850);
    }

    public function test_new_booking_opens_auction_until_driver_allotted_and_shows_allotted_status(): void
    {
        // Booking creation automatically opens an auction with no expiry deadline.
        $booking = $this->booking('Pune', 'Mumbai');

        $feed = $this->actingAs($this->driverUser)
            ->getJson('/api/driver/auctions')
            ->assertStatus(200)
            ->assertJsonPath('count', 1);

        $auction = $feed->json('auctions.0');
        $this->assertSame($booking->booking_reference, $auction['booking_reference']);
        $this->assertNull($auction['closes_at']);
        $this->assertTrue($auction['is_open']);

        // Allotting a driver marks the auction as Allotted (awarded) and closes it.
        $this->actingAs($this->admin)
            ->postJson("/api/admin/bookings/{$booking->id}/allot-driver", [
                'driver_id' => $this->driver->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('booking.status', 'Driver Assigned');

        $session = BiddingSession::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame(BiddingSession::STATUS_AWARDED, $session->status);

        // No longer in open auctions; appears in recent_allotted with status = allotted.
        $afterAllot = $this->actingAs($this->driverUser)
            ->getJson('/api/driver/auctions')
            ->assertStatus(200)
            ->assertJsonPath('count', 0);

        $this->assertSame($booking->booking_reference, $afterAllot->json('recent_allotted.0.booking_reference'));
        $this->assertSame('allotted', $afterAllot->json('recent_allotted.0.status'));
        $this->assertTrue($afterAllot->json('recent_allotted.0.allotted_to_you'));

        // Public bidding page displays "Allotted".
        $this->get("/bid/{$session->token}")
            ->assertStatus(200)
            ->assertSee('Allotted');
    }
}
