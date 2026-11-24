<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\PlatformSetting;
use App\Models\SeatLayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * P0-4 coupons, P0-6 berth allocation, P0-7 references, P0-8 booking privacy,
 * P1-1 distance.
 */
class BookingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Berth sales are behind an admin feature flag. These tests are about
        // inventory integrity, so make sure the module is on regardless of the
        // seeded default.
        PlatformSetting::set('enable_schedules_and_berths', '1');
    }

    private function cabPayload(array $overrides = []): array
    {
        return array_merge([
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Baner, Pune',
            'drop_location' => 'Panjim, Goa',
            'customer_name' => 'Integrity Tester',
            'customer_phone' => '+91 91111 22222',
            'fleet_category' => 'suv',
            'include_tolls' => true,
        ], $overrides);
    }

    // ---------------------------------------------------------------- P0-4

    public function test_expired_coupon_is_ignored(): void
    {
        Coupon::create([
            'code' => 'EXPIRED50',
            'type' => 'flat',
            'value' => 50,
            'min_spend' => 0,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/bookings', $this->cabPayload(['coupon_code' => 'EXPIRED50']));

        $response->assertStatus(200)
            ->assertJsonPath('quote.discount_fare', 0)
            ->assertJsonPath('quote.coupon.applied', false);

        $this->assertStringContainsString('expired', $response->json('quote.coupon.message'));
        $this->assertSame(0, (int) Coupon::where('code', 'EXPIRED50')->value('used_count'));
    }

    public function test_coupon_past_its_usage_limit_is_ignored(): void
    {
        $coupon = Coupon::create([
            'code' => 'ONCEONLY',
            'type' => 'flat',
            'value' => 100,
            'min_spend' => 0,
            'is_active' => true,
            'usage_limit' => 1,
            'used_count' => 1,
        ]);

        $response = $this->postJson('/api/bookings', $this->cabPayload(['coupon_code' => 'ONCEONLY']));

        $response->assertStatus(200)->assertJsonPath('quote.discount_fare', 0);
        $this->assertStringContainsString('usage limit', $response->json('quote.coupon.message'));
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
    }

    public function test_coupon_below_minimum_spend_is_ignored(): void
    {
        Coupon::create([
            'code' => 'BIGSPEND',
            'type' => 'flat',
            'value' => 500,
            'min_spend' => 999999,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/bookings', $this->cabPayload(['coupon_code' => 'BIGSPEND']));

        $response->assertStatus(200)->assertJsonPath('quote.discount_fare', 0);
        $this->assertStringContainsString('minimum spend', $response->json('quote.coupon.message'));
    }

    public function test_valid_coupon_is_applied_and_counted_once(): void
    {
        $coupon = Coupon::create([
            'code' => 'SAVE100',
            'type' => 'flat',
            'value' => 100,
            'min_spend' => 0,
            'is_active' => true,
            'usage_limit' => 5,
            'used_count' => 0,
        ]);

        $response = $this->postJson('/api/bookings', $this->cabPayload(['coupon_code' => 'SAVE100']));

        $response->assertStatus(200)
            ->assertJsonPath('quote.coupon.applied', true)
            ->assertJsonPath('quote.discount_fare', 100);

        $this->assertSame(1, (int) $coupon->fresh()->used_count);

        $booking = $response->json('booking');
        $this->assertEquals($response->json('quote.total_amount'), (float) $booking['total_amount']);
    }

    public function test_inactive_coupon_is_rejected(): void
    {
        Coupon::create([
            'code' => 'DISABLED',
            'type' => 'flat',
            'value' => 100,
            'min_spend' => 0,
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/bookings', $this->cabPayload(['coupon_code' => 'DISABLED']));

        $response->assertStatus(200)->assertJsonPath('quote.discount_fare', 0);
        $this->assertStringContainsString('invalid or inactive', $response->json('quote.coupon.message'));
    }

    // ---------------------------------------------------------------- P0-6

    public function test_berths_are_marked_booked_when_the_booking_succeeds(): void
    {
        $response = $this->postJson('/api/bookings', [
            'service_type' => 'bus',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Swargate',
            'drop_location' => 'Panjim',
            'customer_name' => 'Berth Tester',
            'customer_phone' => '+91 93333 44444',
            'selected_units' => ['L6', 'L7'],
            'coach_code' => '8092',
        ]);

        $response->assertStatus(200);

        foreach (['L6', 'L7'] as $berth) {
            $this->assertDatabaseHas('seat_layouts', [
                'coach_code' => '8092',
                'berth_number' => $berth,
                'is_booked' => true,
            ]);
        }
    }

    public function test_a_berth_cannot_be_booked_twice(): void
    {
        $payload = [
            'service_type' => 'bus',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Swargate',
            'drop_location' => 'Panjim',
            'customer_name' => 'First Passenger',
            'customer_phone' => '+91 93333 55555',
            'selected_units' => ['L8'],
            'coach_code' => '8092',
        ];

        $this->postJson('/api/bookings', $payload)->assertStatus(200);

        $second = $this->postJson('/api/bookings', array_merge($payload, [
            'customer_name' => 'Second Passenger',
            'customer_phone' => '+91 93333 66666',
        ]));

        $second->assertStatus(409)
            ->assertJsonPath('code', 'berth_unavailable')
            ->assertJsonPath('berths.0', 'L8');

        // The rejected attempt must not have created a second booking.
        $this->assertSame(0, Booking::where('customer_name', 'Second Passenger')->count());
    }

    public function test_already_sold_seeded_berth_is_rejected(): void
    {
        // L3 is seeded as is_booked = true.
        $this->postJson('/api/bookings', [
            'service_type' => 'bus',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Swargate',
            'drop_location' => 'Panjim',
            'customer_name' => 'Late Passenger',
            'customer_phone' => '+91 93333 77777',
            'selected_units' => ['L3'],
        ])->assertStatus(409);
    }

    public function test_unknown_berth_is_rejected(): void
    {
        $this->postJson('/api/bookings', [
            'service_type' => 'bus',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Swargate',
            'drop_location' => 'Panjim',
            'customer_name' => 'Wrong Berth',
            'customer_phone' => '+91 93333 88888',
            'selected_units' => ['Z99'],
        ])->assertStatus(422)->assertJsonValidationErrors('selected_units');
    }

    public function test_berth_total_comes_from_seat_layout_prices(): void
    {
        $expected = (float) SeatLayout::where('coach_code', '8092')
            ->whereIn('berth_number', ['L1', 'L2'])
            ->sum('price');

        $response = $this->postJson('/api/bookings', [
            'service_type' => 'bus',
            'from_city' => 'Pune',
            'to_city' => 'Goa',
            'pickup_location' => 'Swargate',
            'drop_location' => 'Panjim',
            'customer_name' => 'Price Tester',
            'customer_phone' => '+91 93333 99999',
            'selected_units' => ['L1', 'L2'],
            'coach_code' => '8092',
        ]);

        $response->assertStatus(200);
        $this->assertEquals($expected, (float) $response->json('booking.base_fare'));
    }

    // ---------------------------------------------------------------- P0-7

    public function test_booking_references_are_generated_and_unique(): void
    {
        $references = [];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/bookings', $this->cabPayload([
                'customer_name' => 'Ref Tester '.$i,
                'customer_phone' => '+91 94444 0000'.$i,
                // Unique coupon-free payload; same trip is fine.
            ]));

            $response->assertStatus(200);
            $reference = $response->json('booking.booking_reference');

            $this->assertMatchesRegularExpression('/^ICW-\d{6}$/', $reference);
            $this->assertNotContains($reference, $references);

            $references[] = $reference;
        }
    }

    public function test_ride_otp_is_four_digits(): void
    {
        $response = $this->postJson('/api/bookings', $this->cabPayload());

        $this->assertMatchesRegularExpression('/^\d{4}$/', (string) $response->json('booking.start_otp'));
    }

    // ---------------------------------------------------------------- P0-8

    public function test_a_reference_alone_does_not_expose_a_booking(): void
    {
        $created = $this->postJson('/api/bookings', $this->cabPayload([
            'customer_name' => 'Private Passenger',
            'customer_phone' => '+91 95555 12345',
        ]))->assertStatus(200);

        $reference = $created->json('booking.booking_reference');

        // Stranger with only the reference.
        $this->getJson("/api/bookings/{$reference}")
            ->assertStatus(403)
            ->assertJsonPath('code', 'booking_forbidden');

        // Wrong phone number.
        $this->getJson("/api/bookings/{$reference}?phone=9999999999")
            ->assertStatus(403);

        // Correct phone number (the guest's only credential).
        $this->getJson("/api/bookings/{$reference}?phone=+91 95555 12345")
            ->assertStatus(200)
            ->assertJsonPath('booking.booking_reference', $reference);
    }

    public function test_signed_lookup_url_from_checkout_is_authorised(): void
    {
        $created = $this->postJson('/api/bookings', $this->cabPayload())->assertStatus(200);

        $lookupUrl = $created->json('lookup_url');
        $this->assertNotEmpty($lookupUrl);
        $this->assertStringContainsString('signature=', (string) $lookupUrl);

        $this->getJson($lookupUrl)->assertStatus(200);
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $created = $this->postJson('/api/bookings', $this->cabPayload())->assertStatus(200);

        $this->getJson($created->json('lookup_url').'0')->assertStatus(403);
    }

    public function test_booking_owner_can_read_their_own_booking_but_not_another_customers(): void
    {
        $owner = User::where('role', 'customer')->firstOrFail();
        $other = User::factory()->create(['role' => 'customer']);

        $created = $this->actingAs($owner)->postJson('/api/bookings', $this->cabPayload([
            'customer_name' => 'Owner Customer',
            'customer_phone' => '+91 95555 77777',
        ]))->assertStatus(200);

        $reference = $created->json('booking.booking_reference');

        $this->assertSame($owner->id, (int) Booking::where('booking_reference', $reference)->value('customer_user_id'));

        $this->actingAs($owner)->getJson("/api/bookings/{$reference}")->assertStatus(200);
        $this->actingAs($other)->getJson("/api/bookings/{$reference}")->assertStatus(403);

        $admin = User::where('role', 'admin')->firstOrFail();
        $this->actingAs($admin)->getJson("/api/bookings/{$reference}")->assertStatus(200);
    }

    public function test_guest_lookup_requires_the_reference_and_phone(): void
    {
        $created = $this->postJson('/api/bookings', $this->cabPayload([
            'customer_name' => 'Lookup Guest',
            'customer_phone' => '+91 95555 88888',
        ]))->assertStatus(200);

        $reference = $created->json('booking.booking_reference');

        $this->postJson('/api/bookings/lookup', [
            'reference' => $reference,
            'phone' => '9000000000',
        ])->assertStatus(404);

        $this->postJson('/api/bookings/lookup', [
            'reference' => $reference,
            'phone' => '+91 95555 88888',
        ])->assertStatus(200)->assertJsonPath('booking.booking_reference', $reference);
    }

    public function test_missing_booking_returns_404_without_leaking_anything(): void
    {
        $this->getJson('/api/bookings/ICW-000000')->assertStatus(404);
        URL::temporarySignedRoute('api.bookings.show', now()->addHour(), ['reference' => 'ICW-000000']);
    }

    // ---------------------------------------------------------------- P1-1

    public function test_unmeasurable_route_fails_instead_of_inventing_a_distance(): void
    {
        $response = $this->postJson('/api/bookings', $this->cabPayload([
            'from_city' => 'Atlantis',
            'to_city' => 'El Dorado',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('code', 'distance_unresolvable');

        // Nothing was persisted for this attempt (the seeder's own booking is
        // unrelated and still the only row).
        $this->assertDatabaseMissing('bookings', ['from_city' => 'Atlantis']);
        $this->assertSame(0, Booking::where('route_summary', 'like', '%El Dorado%')->count());
    }

    public function test_caller_supplied_coordinates_can_measure_an_unlisted_place(): void
    {
        $response = $this->postJson('/api/bookings', $this->cabPayload([
            'from_city' => 'Atlantis',
            'from_lat' => 22.5726,
            'from_lng' => 88.3639,
            'to_city' => 'El Dorado',
            'to_lat' => 21.6266,
            'to_lng' => 87.5074,
        ]));

        $response->assertStatus(200);
        $this->assertGreaterThan(0, (float) $response->json('booking.total_amount'));
    }
}
