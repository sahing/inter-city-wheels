<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverBid;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1-3 — wallet and bid writes are transactional, floored and auditable.
 */
class DriverWalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function driverUser(): User
    {
        return User::where('role', 'driver')->firstOrFail();
    }

    private function driver(): Driver
    {
        $user = $this->driverUser();

        return Driver::where('phone', $user->phone)->firstOrFail();
    }

    private function assignedStartedBooking(Driver $driver, float $total = 2000.0): Booking
    {
        $booking = Booking::where('status', 'Confirmed')->firstOrFail();
        $booking->forceFill([
            'assigned_driver_id' => $driver->id,
            'status' => 'Started',
            'total_amount' => $total,
        ])->save();

        return $booking;
    }

    public function test_trip_completion_credits_the_wallet_and_writes_a_ledger_row(): void
    {
        $driver = $this->driver();
        $booking = $this->assignedStartedBooking($driver, 2000.0);
        $before = (float) $driver->wallet_balance;

        $this->actingAs($this->driverUser())
            ->postJson("/api/driver/trips/{$booking->id}/complete")
            ->assertStatus(200);

        $driver->refresh();

        // 10% platform commission withheld.
        $this->assertEquals($before + 1800.0, (float) $driver->wallet_balance);

        $transaction = WalletTransaction::where('booking_id', $booking->id)
            ->where('reason', WalletTransaction::REASON_TRIP_PAYOUT)
            ->firstOrFail();

        $this->assertEquals(1800.0, (float) $transaction->amount);
        $this->assertEquals((float) $driver->wallet_balance, (float) $transaction->balance_after);
        $this->assertSame(WalletTransaction::TYPE_CREDIT, $transaction->type);
    }

    public function test_a_completed_trip_cannot_be_completed_again_and_never_pays_twice(): void
    {
        $driver = $this->driver();
        $booking = $this->assignedStartedBooking($driver);

        $this->actingAs($this->driverUser())
            ->postJson("/api/driver/trips/{$booking->id}/complete")
            ->assertStatus(200);

        $afterFirst = (float) $driver->fresh()->wallet_balance;

        $this->actingAs($this->driverUser())
            ->postJson("/api/driver/trips/{$booking->id}/complete")
            ->assertStatus(422);

        $this->assertEquals($afterFirst, (float) $driver->fresh()->wallet_balance);
        $this->assertSame(1, WalletTransaction::where('booking_id', $booking->id)->count());
    }

    public function test_withdrawal_above_the_balance_is_refused_and_nothing_moves(): void
    {
        $driver = $this->driver();
        $driver->forceFill(['wallet_balance' => 500])->save();

        $this->actingAs($this->driverUser())
            ->postJson('/api/driver/wallet/withdraw', ['amount' => 5000])
            ->assertStatus(422);

        $this->assertEquals(500.0, (float) $driver->fresh()->wallet_balance);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_withdrawal_within_the_balance_is_debited_and_recorded(): void
    {
        $driver = $this->driver();
        $driver->forceFill(['wallet_balance' => 5000])->save();

        $response = $this->actingAs($this->driverUser())
            ->postJson('/api/driver/wallet/withdraw', ['amount' => 1200])
            ->assertStatus(200);

        $this->assertEquals(3800.0, (float) $driver->fresh()->wallet_balance);
        $this->assertEquals(3800.0, (float) $response->json('balance_after'));

        $transaction = WalletTransaction::where('reason', WalletTransaction::REASON_WITHDRAWAL)->firstOrFail();

        $this->assertSame(WalletTransaction::TYPE_DEBIT, $transaction->type);
        $this->assertEquals(1200.0, (float) $transaction->amount);
        $this->assertEquals(3800.0, (float) $transaction->balance_after);
    }

    public function test_bid_for_a_real_booking_is_persisted(): void
    {
        $driver = $this->driver();
        $booking = Booking::firstOrFail();

        $response = $this->actingAs($this->driverUser())->postJson('/api/bids/submit', [
            'trip_id' => $booking->booking_reference,
            'from_location' => 'Pune',
            'to_location' => 'Goa',
            'distance_km' => 450,
            'bid_amount' => 3200,
            'driver_id' => $driver->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('bid.persisted', true);

        $this->assertDatabaseHas('driver_bids', [
            'booking_id' => $booking->id,
            'driver_id' => $driver->id,
            'bid_amount' => 3200,
        ]);
    }

    public function test_bid_with_an_unresolvable_trip_is_reported_as_unpersisted(): void
    {
        $driver = $this->driver();

        $response = $this->actingAs($this->driverUser())->postJson('/api/bids/submit', [
            'trip_id' => 'trip-not-a-booking',
            'from_location' => 'Pune',
            'to_location' => 'Goa',
            'distance_km' => 450,
            'bid_amount' => 3200,
            'driver_id' => $driver->id,
        ]);

        $response->assertStatus(200)->assertJsonPath('bid.persisted', false);
        $this->assertSame(0, DriverBid::count());
    }

    public function test_driver_without_a_linked_profile_gets_403_rather_than_another_drivers_wallet(): void
    {
        $stranger = User::factory()->create([
            'role' => 'driver',
            'phone' => '+91 90000 00009',
        ]);

        $this->actingAs($stranger)
            ->postJson('/api/driver/wallet/withdraw', ['amount' => 100])
            ->assertStatus(403);

        $this->actingAs($stranger)->getJson('/api/driver/profile')->assertStatus(404);
    }
}
