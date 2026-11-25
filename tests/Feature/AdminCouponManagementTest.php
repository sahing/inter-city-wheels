<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin coupon issuance (the register the admin panel writes to) plus proof
 * that a coupon issued there is immediately redeemable on the booking screen.
 */
class AdminCouponManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
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
            'customer_name' => 'Coupon Tester',
            'customer_phone' => '+91 93333 44444',
            'fleet_category' => 'suv',
            'include_tolls' => true,
        ], $overrides);
    }

    // ------------------------------------------------------------ issuance

    public function test_admin_can_issue_a_coupon(): void
    {
        $response = $this->actingAs($this->admin())->postJson('/api/admin/coupons', [
            'code' => 'monsoon20',
            'type' => 'percentage',
            'value' => 20,
            'max_discount' => 400,
            'min_spend' => 1500,
            'usage_limit' => 50,
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('coupon.code', 'MONSOON20');

        // Code is normalised to uppercase on the way in.
        $this->assertDatabaseHas('coupons', [
            'code' => 'MONSOON20',
            'type' => 'percentage',
            'usage_limit' => 50,
        ]);
    }

    public function test_admin_cannot_issue_duplicate_code(): void
    {
        Coupon::create(['code' => 'TAKEN', 'type' => 'flat', 'value' => 100, 'min_spend' => 0, 'is_active' => true]);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/coupons', [
                'code' => 'TAKEN',
                'type' => 'flat',
                'value' => 50,
            ])
            ->assertStatus(422);
    }

    public function test_percentage_coupon_cannot_exceed_one_hundred(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/coupons', [
                'code' => 'TOOMUCH',
                'type' => 'percentage',
                'value' => 150,
            ])
            ->assertStatus(422);
    }

    public function test_admin_can_update_toggle_and_delete_coupon(): void
    {
        $created = $this->actingAs($this->admin())->postJson('/api/admin/coupons', [
            'code' => 'EDITME',
            'type' => 'flat',
            'value' => 100,
        ])->json('coupon');

        $this->actingAs($this->admin())
            ->putJson("/api/admin/coupons/{$created['id']}", [
                'code' => 'EDITME',
                'type' => 'flat',
                'value' => 250,
                'is_active' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('coupon.value', '250.00');

        $this->actingAs($this->admin())
            ->postJson("/api/admin/coupons/{$created['id']}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('coupon.is_active', false);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/coupons/{$created['id']}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('coupons', ['id' => $created['id']]);
    }

    public function test_coupon_list_exposes_redeemability_metadata(): void
    {
        Coupon::create(['code' => 'LIVE10', 'type' => 'percentage', 'value' => 10, 'min_spend' => 0, 'is_active' => true]);
        Coupon::create([
            'code' => 'DEAD10',
            'type' => 'percentage',
            'value' => 10,
            'min_spend' => 0,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->admin())->getJson('/api/admin/coupons');
        $response->assertStatus(200);

        $coupons = collect($response->json('coupons'))->keyBy('code');

        $this->assertTrue($coupons['LIVE10']['redeemable']);
        $this->assertFalse($coupons['DEAD10']['redeemable']);
        $this->assertIsInt($response->json('meta.total'));
    }

    public function test_non_admin_cannot_manage_coupons(): void
    {
        $customer = User::where('role', 'customer')->firstOrFail();

        $this->getJson('/api/admin/coupons')->assertStatus(401);
        $this->actingAs($customer)->getJson('/api/admin/coupons')->assertStatus(403);
        $this->actingAs($customer)
            ->postJson('/api/admin/coupons', ['code' => 'NOPE', 'type' => 'flat', 'value' => 10])
            ->assertStatus(403);
    }

    // ------------------------------------------- issued coupon → booking

    public function test_coupon_issued_by_admin_is_redeemable_at_booking(): void
    {
        $issued = $this->actingAs($this->admin())->postJson('/api/admin/coupons', [
            'code' => 'ADMIN100',
            'type' => 'flat',
            'value' => 100,
            'min_spend' => 0,
            'usage_limit' => 1,
            'is_active' => true,
        ])->json('coupon');

        $this->assertNotNull($issued);

        // A signed-in customer books and applies the freshly issued code.
        $response = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', $this->cabPayload(['coupon_code' => 'ADMIN100']));

        $response->assertStatus(200);
        $response->assertJsonPath('quote.coupon.applied', true);
        $response->assertJsonPath('quote.coupon.code', 'ADMIN100');
        $this->assertEquals(100, (int) round($response->json('quote.coupon.discount')));

        // Redemption is counted exactly once and the coupon is now exhausted.
        $this->assertSame(1, (int) Coupon::where('code', 'ADMIN100')->value('used_count'));

        $again = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', $this->cabPayload(['coupon_code' => 'ADMIN100']));
        $again->assertStatus(200);
        $again->assertJsonPath('quote.coupon.applied', false);
        $this->assertStringContainsString('usage limit', (string) $again->json('quote.coupon.message'));
    }

    public function test_admin_issued_expired_coupon_is_rejected_at_booking(): void
    {
        $this->actingAs($this->admin())->postJson('/api/admin/coupons', [
            'code' => 'PASTDUE',
            'type' => 'flat',
            'value' => 200,
            'min_spend' => 0,
            'expires_at' => now()->subDay()->toDateTimeString(),
            'is_active' => true,
        ])->assertStatus(201);

        $response = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', $this->cabPayload(['coupon_code' => 'PASTDUE']));

        $response->assertStatus(200);
        $response->assertJsonPath('quote.coupon.applied', false);
        $this->assertStringContainsString('expired', (string) $response->json('quote.coupon.message'));
        $this->assertSame(0, (int) Coupon::where('code', 'PASTDUE')->value('used_count'));
    }

    public function test_admin_issued_coupon_quote_agrees_with_booking_charge(): void
    {
        $this->actingAs($this->admin())->postJson('/api/admin/coupons', [
            'code' => 'QUOTE25',
            'type' => 'percentage',
            'value' => 25,
            'max_discount' => 300,
            'min_spend' => 0,
            'is_active' => true,
        ])->assertStatus(201);

        $quote = $this->getJson('/api/routes/calculate?from=Pune&to=Goa&fleet=suv&include_tolls=1&coupon=QUOTE25');
        $quote->assertStatus(200);
        $quote->assertJsonPath('quote.coupon.applied', true);

        $booking = $this->actingAs(User::where('role', 'customer')->firstOrFail())->postJson('/api/bookings', $this->cabPayload(['coupon_code' => 'QUOTE25']));
        $booking->assertStatus(200);

        $this->assertSame(
            (int) round($quote->json('quote.total_amount')),
            (int) round($booking->json('quote.total_amount')),
            'The quote shown to the customer must equal what the booking charges.'
        );
    }
}
