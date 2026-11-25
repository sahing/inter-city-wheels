<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirming a booking requires a signed-in account.
 *
 * The checkout flow ships a one-tap login gate for guests: the trip is stashed
 * client-side, the user signs in, and the pending booking is resumed. These
 * tests cover the server side of that contract.
 */
class BookingRequiresLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function payload(): array
    {
        return [
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Baner, Pune',
            'drop_location' => 'Panjim, Goa',
            'customer_name' => 'Gate Tester',
            'customer_phone' => '+91 97777 11111',
            'fleet_category' => 'suv',
            'include_tolls' => true,
        ];
    }

    public function test_a_guest_cannot_confirm_a_booking(): void
    {
        $this->postJson('/api/bookings', $this->payload())->assertStatus(401);

        $this->assertDatabaseMissing('bookings', ['customer_name' => 'Gate Tester']);
    }

    public function test_one_tap_login_then_booking_links_the_trip_to_the_account(): void
    {
        // One tap: no credentials required, same endpoint the gate calls.
        $this->postJson('/login/quick-demo', ['role' => 'customer'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // The session is now authenticated.
        $this->getJson('/api/auth/user')->assertStatus(200);

        $userId = (int) User::where('email', 'customer@intercitywheels.com')->value('id');

        $booking = $this->postJson('/api/bookings', $this->payload());
        $booking->assertStatus(200);

        $reference = $booking->json('booking.booking_reference');
        $this->assertNotEmpty($reference);

        // The trip belongs to the signed-in account, not to an anonymous guest.
        $this->assertSame($userId, (int) Booking::where('booking_reference', $reference)->value('customer_user_id'));

        // Ownership alone is enough to reopen the ticket — no phone re-entry.
        $this->getJson("/api/bookings/{$reference}")->assertStatus(200);
    }

    public function test_logging_out_removes_the_ability_to_confirm_a_booking(): void
    {
        $this->actingAs(User::where('role', 'customer')->firstOrFail())
            ->postJson('/api/bookings', $this->payload())
            ->assertStatus(200);

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/bookings', $this->payload())->assertStatus(401);
    }

    public function test_quote_and_browse_stay_available_to_guests(): void
    {
        // The gate must not block the funnel — only the confirmation.
        $this->getJson('/api/routes/calculate?from=Pune&to=Goa')->assertStatus(200);
        $this->getJson('/api/locations')->assertStatus(200);
    }
}
