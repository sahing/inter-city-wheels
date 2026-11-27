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

    public function test_driver_payment_history_shows_collected_parts_bidding_amount_and_clarification(): void
    {
        $driver = $this->driver();
        $driverUser = $this->driverUser();
        $before = (float) $driver->wallet_balance;

        // 1. Part-pay trip won via driver bidding: customer paid ₹1,000 online (20% of ₹5,000),
        //    driver's winning bid is ₹4,300, driver collects ₹4,000 balance -> +₹300 wallet credit.
        $booking = $this->assignedStartedBooking($driver, 5000.0);
        $bid = DriverBid::create([
            'booking_id' => $booking->id,
            'driver_id' => $driver->id,
            'bid_amount' => 4300.0,
            'status' => 'accepted',
            'is_winning' => true,
        ]);
        $booking->forceFill([
            'payment_mode' => 'part_pay',
            'advance_percentage' => 20,
            'advance_amount' => 1000.0,
            'balance_amount' => 4000.0,
            'payment_status' => 'partial_paid',
            'payment_collection_by' => 'driver',
            'assigned_bid_id' => $bid->id,
            'driver_payout_amount' => 4300.0,
        ])->save();

        $completeRes = $this->actingAs($driverUser)
            ->postJson("/api/driver/trips/{$booking->id}/complete", [
                'collected_by_driver' => true,
                'collected_amount' => 4000.0,
            ]);

        $completeRes->assertOk()
            ->assertJsonPath('driver_collected_amount', 4000)
            ->assertJsonPath('net_wallet_delta', 300)
            ->assertJsonPath('trip_breakdown.bidding_amount', 4300)
            ->assertJsonPath('trip_breakdown.platform_received_amount', 1000)
            ->assertJsonPath('trip_breakdown.driver_collected_amount', 4000)
            ->assertJsonPath('trip_breakdown.wallet_credited_part', 300);

        // 2. Full customer collection where collected ₹4,000 > winning bid ₹3,200 -> -₹800 wallet debit
        $cashBooking = Booking::create([
            'booking_reference' => 'ICW-CASH-9901',
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'route_summary' => 'Kolkata → Siliguri',
            'pickup_location' => 'Esplanade',
            'drop_location' => 'Siliguri Junction',
            'customer_name' => 'Ria Sen',
            'customer_phone' => '9876500001',
            'passengers' => [['name' => 'Ria Sen']],
            'selected_units' => ['suv'],
            'base_fare' => 3800,
            'toll_fare' => 200,
            'addon_fare' => 0,
            'discount_fare' => 0,
            'total_amount' => 4000,
            'payment_mode' => 'cash',
            'advance_percentage' => 0,
            'advance_amount' => 0,
            'balance_amount' => 4000,
            'payment_status' => 'pay_on_pickup',
            'payment_collection_by' => 'driver',
            'status' => 'Started',
            'assigned_driver_id' => $driver->id,
            'start_otp' => '4455',
        ]);
        $cashBid = DriverBid::create([
            'booking_id' => $cashBooking->id,
            'driver_id' => $driver->id,
            'bid_amount' => 3200.0,
            'status' => 'accepted',
            'is_winning' => true,
        ]);
        $cashBooking->forceFill([
            'assigned_bid_id' => $cashBid->id,
            'driver_payout_amount' => 3200.0,
        ])->save();

        $this->actingAs($driverUser)
            ->postJson("/api/driver/trips/{$cashBooking->id}/complete", [
                'collected_by_driver' => true,
            ])
            ->assertOk()
            ->assertJsonPath('driver_collected_amount', 4000)
            ->assertJsonPath('net_wallet_delta', -800);

        // 3. Exact match where collected ₹3,200 == winning bid ₹3,200 -> records ₹0 settled ledger entry
        $exactBooking = Booking::create([
            'booking_reference' => 'ICW-EXACT-9902',
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'route_summary' => 'Pune → Nashik',
            'pickup_location' => 'Shivajinagar',
            'drop_location' => 'Nashik CBS',
            'customer_name' => 'Amit Joshi',
            'customer_phone' => '9876500002',
            'passengers' => [['name' => 'Amit Joshi']],
            'selected_units' => ['suv'],
            'base_fare' => 3800,
            'toll_fare' => 200,
            'addon_fare' => 0,
            'discount_fare' => 0,
            'total_amount' => 4000,
            'payment_mode' => 'part_pay',
            'advance_percentage' => 20,
            'advance_amount' => 800,
            'balance_amount' => 3200,
            'payment_status' => 'partial_paid',
            'payment_collection_by' => 'driver',
            'status' => 'Started',
            'assigned_driver_id' => $driver->id,
            'start_otp' => '7788',
        ]);
        $exactBid = DriverBid::create([
            'booking_id' => $exactBooking->id,
            'driver_id' => $driver->id,
            'bid_amount' => 3200.0,
            'status' => 'accepted',
            'is_winning' => true,
        ]);
        $exactBooking->forceFill([
            'assigned_bid_id' => $exactBid->id,
            'driver_payout_amount' => 3200.0,
        ])->save();

        $this->actingAs($driverUser)
            ->postJson("/api/driver/trips/{$exactBooking->id}/complete", [
                'collected_by_driver' => true,
            ])
            ->assertOk()
            ->assertJsonPath('driver_collected_amount', 3200)
            ->assertJsonPath('net_wallet_delta', 0);

        // Verify payment-history API returns trip-wise records, bid amounts, parts received & clarifications
        $historyRes = $this->actingAs($driverUser)->getJson('/api/driver/payment-history');
        $historyRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.total_driver_collected', 11200)
            ->assertJsonPath('summary.total_driver_earnings', 10700);
        $this->assertEquals($before + 300 - 800, (float) $historyRes->json('summary.wallet_balance'));

        $tripRecords = collect($historyRes->json('trip_records'));
        $partTrip = $tripRecords->firstWhere('booking_reference', $booking->booking_reference);
        $this->assertNotNull($partTrip);
        $this->assertEquals(4300.0, $partTrip['bidding_amount']);
        $this->assertEquals(1000.0, $partTrip['platform_received_amount']);
        $this->assertEquals(4000.0, $partTrip['driver_collected_amount']);
        $this->assertEquals(300.0, $partTrip['wallet_credited_part']);
        $this->assertEquals(4300.0, $partTrip['total_received_by_driver']);
        $this->assertStringContainsString('collected ₹4,000', $partTrip['clarification']);

        $exactTx = WalletTransaction::where('booking_id', $exactBooking->id)->first();
        $this->assertNotNull($exactTx);
        $this->assertEquals(0.0, (float) $exactTx->amount);
        $this->assertStringContainsString('₹0 wallet adjustment', (string) $exactTx->description);

        // Rendered Driver Wallet page displays trip-wise payment part record, bid badge, and clarification
        $html = $this->actingAs($driverUser)->get('/')->getContent();
        $this->assertStringContainsString('Trip-Wise Payment Part Record', $html);
        $this->assertStringContainsString('Winning Bid: ₹4,300', $html);
        $this->assertStringContainsString('Collected by You', $html);
        $this->assertStringContainsString('Total Part Received', $html);
        $this->assertStringContainsString('platform margin settled from wallet', $html);

        // Customers cannot access driver payment history API
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->getJson('/api/driver/payment-history')->assertStatus(403);
    }
}
