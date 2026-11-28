<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "After booking, land on my trip history with All / Active / Completed filters."
 *
 * The list itself is server-rendered (so it is correct without JavaScript); the
 * filters are data attributes the browser toggles, and the counts are rendered
 * from the same collection.
 */
class MyTripsHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // A dedicated customer so the seeded demo booking (which belongs to the
        // seeded demo account) cannot skew the counts under test.
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'name' => 'History Tester',
            'phone' => '+91 95555 12345',
        ]);
    }

    private function bookAs(User $user, array $overrides = []): Booking
    {
        $response = $this->actingAs($user)->postJson('/api/bookings', array_merge([
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Baner, Pune',
            'drop_location' => 'Panjim, Goa',
            'customer_name' => $user->name,
            'customer_phone' => $user->phone,
            'fleet_category' => 'suv',
        ], $overrides))->assertStatus(200);

        return Booking::where('booking_reference', $response->json('booking.booking_reference'))->firstOrFail();
    }

    public function test_history_screen_renders_all_active_and_completed_filters(): void
    {
        $this->bookAs($this->customer, ['customer_name' => 'Filter Tester']);

        $response = $this->actingAs($this->customer)->get('/');

        $response->assertStatus(200)
            ->assertSee('My Trips &amp; Booking History', false)
            ->assertSee('trips-filters', false)
            ->assertSee('data-filter="all"', false)
            ->assertSee('data-filter="active"', false)
            ->assertSee('data-filter="completed"', false)
            ->assertSee('trips-count-all', false)
            ->assertSee('id="trips-empty"', false);
    }

    public function test_counts_and_buckets_reflect_the_customers_own_bookings(): void
    {
        $active = $this->bookAs($this->customer, ['customer_name' => 'Active Trip']);
        $completed = $this->bookAs($this->customer, ['customer_name' => 'Completed Trip']);
        $completed->forceFill(['status' => 'Completed'])->save();

        $response = $this->actingAs($this->customer)->get('/');
        $html = $response->getContent();

        $response->assertSee('data-bucket="active"', false)
            ->assertSee('data-bucket="completed"', false);

        // One of each bucket.
        $this->assertSame(1, substr_count($html, 'data-bucket="active"'));
        $this->assertSame(1, substr_count($html, 'data-bucket="completed"'));

        // Counters: 2 total, 1 active, 1 completed.
        $this->assertMatchesRegularExpression('/id="trips-count-all">2</', $html);
        $this->assertMatchesRegularExpression('/id="trips-count-active">1</', $html);
        $this->assertMatchesRegularExpression('/id="trips-count-completed">1</', $html);

        $response->assertSee('1 active', false);
        $response->assertSee('1 completed', false);
        $response->assertSee($active->booking_reference);
        $response->assertSee($completed->booking_reference);
    }

    public function test_a_cancelled_trip_is_listed_under_all_only(): void
    {
        $cancelled = $this->bookAs($this->customer, ['customer_name' => 'Cancelled Trip']);
        $cancelled->forceFill(['status' => 'Cancelled'])->save();

        $html = $this->actingAs($this->customer)->get('/')->getContent();

        $this->assertStringContainsString('data-bucket="cancelled"', $html);
        // It must not be counted as active or completed.
        $this->assertMatchesRegularExpression('/id="trips-count-active">0</', $html);
        $this->assertMatchesRegularExpression('/id="trips-count-completed">0</', $html);
        $this->assertMatchesRegularExpression('/id="trips-count-all">1</', $html);
    }

    public function test_another_customers_bookings_never_appear(): void
    {
        $other = User::factory()->create([
            'role' => 'customer',
            'phone' => '+91 90000 11111',
            'name' => 'Someone Else',
        ]);

        $theirs = $this->bookAs($other, ['customer_name' => 'Someone Else']);

        $response = $this->actingAs($this->customer)->get('/');

        $response->assertStatus(200);
        $response->assertDontSee($theirs->booking_reference);
    }

    public function test_guests_get_a_sign_in_and_track_panel_instead_of_a_history_list(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('Sign in to see your trip history', false)
            ->assertSee('trips-track-reference', false)
            ->assertSee('trips-track-phone', false)
            ->assertSee('My Trips')
            ->assertDontSee('data-filter="completed"', false);
    }

    public function test_my_trips_tab_can_be_deep_linked(): void
    {
        $guest = $this->get('/?tab=my-bookings');
        $guest->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/<section id="tab-my-bookings" class="tab-pane active"/',
            $guest->getContent()
        );

        $customer = $this->actingAs($this->customer)->get('/?tab=my-bookings');
        $customer->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '/<section id="tab-my-bookings" class="tab-pane active"/',
            $customer->getContent()
        );
    }

    public function test_a_disallowed_tab_deep_link_is_ignored_for_the_role(): void
    {
        // A customer must not be able to deep-link into the admin console.
        $html = $this->actingAs($this->customer)->get('/?tab=admin')->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<div class="admin-section[^"]*" id="admin-sec-dashboard"[^>]*>/',
            $html
        );
    }

    public function test_history_cards_expose_the_otp_and_a_boarding_pass_action(): void
    {
        $booking = $this->bookAs($this->customer, ['customer_name' => 'Otp Card Tester']);

        $html = $this->actingAs($this->customer)->get('/')->getContent();

        $this->assertStringContainsString($booking->start_otp, $html);
        $this->assertStringContainsString("openTicketModal('{$booking->booking_reference}')", $html);
        $this->assertStringContainsString('Digital Boarding Pass', $html);
    }

    public function test_checkout_landing_opens_history_and_highlights_the_new_booking(): void
    {
        $booking = $this->bookAs($this->customer, ['customer_name' => 'Landing Tester']);

        $response = $this->actingAs($this->customer)->get('/?booked='.$booking->booking_reference);
        $html = $response->getContent();

        $response->assertStatus(200);

        // The history tab is the active pane.
        $this->assertMatchesRegularExpression('/<section id="tab-my-bookings" class="tab-pane active"/', $html);

        // The confirmation banner names the booking.
        $response->assertSee('Booking '.$booking->booking_reference.' confirmed', false);

        // And the card itself carries both the highlight class and the flag.
        $this->assertMatchesRegularExpression(
            '/class="customer-trip-card is-new" id="trip-card-'.preg_quote($booking->booking_reference, '/').'"/',
            $html
        );
        $this->assertStringContainsString('trip-new-flag', $html);
    }

    public function test_a_malformed_booked_parameter_is_ignored(): void
    {
        $response = $this->actingAs($this->customer)->get('/?booked="><script>alert(1)</script>');
        $html = $response->getContent();

        $response->assertStatus(200);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('confirmed.</strong>', $html);
        // Falls back to the default tab, not the history pane.
        $this->assertDoesNotMatchRegularExpression('/<section id="tab-my-bookings" class="tab-pane active"/', $html);
    }
}
