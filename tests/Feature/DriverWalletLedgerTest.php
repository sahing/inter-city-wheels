<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverBid;
use App\Models\DriverPayoutRequest;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_driver_payout_request_deducts_wallet_and_admin_processes_manual_gateway_and_reject_refund(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $driverUser = $this->driverUser();
        $driver = $this->driver();
        $driver->forceFill([
            'wallet_balance' => 10000,
            'preferred_payout_method' => 'upi',
            'payout_upi_id' => 'vikram.cab@okicici',
            'payout_upi_name' => 'Vikram Chauffeur',
            'bank_account_holder' => null,
            'bank_name' => null,
            'bank_account_number' => null,
            'bank_ifsc' => null,
        ])->save();

        // Requesting bank payout without configured bank profile is rejected with 422
        $this->actingAs($driverUser)->postJson('/api/driver/wallet/withdraw', [
            'amount' => 1500,
            'payout_method' => 'bank',
        ])->assertStatus(422);

        // 1. Driver submits UPI payout request -> deducts wallet & creates pending payout request
        $req1 = $this->actingAs($driverUser)->postJson('/api/driver/wallet/withdraw', [
            'amount' => 2000,
            'payout_method' => 'upi',
        ]);
        $req1->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('balance_after', 8000)
            ->assertJsonPath('payout_request.status', 'pending')
            ->assertJsonPath('payout_request.payout_method', 'upi');

        $pr1Id = (int) $req1->json('payout_request.id');
        $this->assertEquals(8000.0, (float) $driver->fresh()->wallet_balance);

        // Non-admin cannot access or process admin payout endpoints
        $this->actingAs($driverUser)->getJson('/api/admin/payout-requests')->assertStatus(403);
        $this->actingAs($driverUser)->postJson("/api/admin/payout-requests/{$pr1Id}/process", [
            'action' => 'approve_manual',
            'transaction_reference' => 'UTR123',
        ])->assertStatus(403);

        // Manual approval requires a transaction reference
        $this->actingAs($admin)->postJson("/api/admin/payout-requests/{$pr1Id}/process", [
            'action' => 'approve_manual',
        ])->assertStatus(422);

        // Admin approves via Manual Mode with UTR reference
        $manualApprove = $this->actingAs($admin)->postJson("/api/admin/payout-requests/{$pr1Id}/process", [
            'action' => 'approve_manual',
            'transaction_reference' => 'UPIUTR99887766',
            'admin_notes' => 'Transferred via ICICI Business UPI',
        ]);
        $manualApprove->assertOk()
            ->assertJsonPath('payout_request.status', 'paid')
            ->assertJsonPath('payout_request.payout_mode', 'manual')
            ->assertJsonPath('payout_request.transaction_reference', 'UPIUTR99887766');

        // Re-processing an already paid request is rejected
        $this->actingAs($admin)->postJson("/api/admin/payout-requests/{$pr1Id}/process", [
            'action' => 'reject',
        ])->assertStatus(422);

        // 2. Save Bank details, request Bank payout, and approve via Payment Gateway API Mode
        $this->actingAs($driverUser)->postJson('/api/driver/profile/payout-details', [
            'preferred_payout_method' => 'bank',
            'payout_upi_id' => 'vikram.cab@okicici',
            'bank_account_holder' => 'Vikram Chauffeur',
            'bank_name' => 'HDFC Bank',
            'bank_account_number' => '50100234567890',
            'bank_ifsc' => 'HDFC0001029',
        ])->assertOk();

        $req2 = $this->actingAs($driverUser)->postJson('/api/driver/wallet/withdraw', [
            'amount' => 3000,
            'payout_method' => 'bank',
        ])->assertOk();
        $pr2Id = (int) $req2->json('payout_request.id');
        $this->assertEquals(5000.0, (float) $driver->fresh()->wallet_balance);

        PlatformSetting::set('payment_gateway', 'razorpay');
        PlatformSetting::set('razorpay_key_id', 'rzp_test_payout1');
        PlatformSetting::set('razorpay_key_secret', 'rzp_secret_payout1');
        PlatformSetting::set('razorpayx_account_number', '2323230011223344');

        Http::fake([
            'https://api.razorpay.com/v1/payouts' => Http::response([
                'id' => 'pout_RzpGateway001',
                'status' => 'processing',
                'utr' => 'IMPSUTR55443322',
            ], 200),
        ]);

        $gwApprove = $this->actingAs($admin)->postJson("/api/admin/payout-requests/{$pr2Id}/process", [
            'action' => 'approve_gateway',
            'admin_notes' => 'Auto-dispatched via RazorpayX',
        ]);
        $gwApprove->assertOk()
            ->assertJsonPath('payout_request.status', 'paid')
            ->assertJsonPath('payout_request.payout_mode', 'gateway')
            ->assertJsonPath('payout_request.gateway_provider', 'razorpayx')
            ->assertJsonPath('payout_request.transaction_reference', 'IMPSUTR55443322');

        // 3. Submit a third payout request and Admin rejects it -> refunds ₹1,500 back to wallet
        $req3 = $this->actingAs($driverUser)->postJson('/api/driver/wallet/withdraw', [
            'amount' => 1500,
            'payout_method' => 'upi',
        ])->assertOk();
        $pr3Id = (int) $req3->json('payout_request.id');
        $this->assertEquals(3500.0, (float) $driver->fresh()->wallet_balance);

        $rejectRes = $this->actingAs($admin)->postJson("/api/admin/payout-requests/{$pr3Id}/process", [
            'action' => 'reject',
            'admin_notes' => 'Weekly reconciliation hold',
        ]);
        $rejectRes->assertOk()
            ->assertJsonPath('payout_request.status', 'rejected');

        // Wallet balance refunded back to ₹5,000
        $this->assertEquals(5000.0, (float) $driver->fresh()->wallet_balance);
        $pr3 = DriverPayoutRequest::findOrFail($pr3Id);
        $this->assertNotNull($pr3->refund_wallet_transaction_id);

        // Driver payment history lists all 3 payout requests with their status & references
        $history = $this->actingAs($driverUser)->getJson('/api/driver/payment-history')->assertOk();
        $reqs = collect($history->json('payout_requests'));
        $this->assertCount(3, $reqs);
        $this->assertSame('UPIUTR99887766', $reqs->firstWhere('id', $pr1Id)['transaction_reference']);
        $this->assertSame('IMPSUTR55443322', $reqs->firstWhere('id', $pr2Id)['transaction_reference']);
        $this->assertSame('rejected', $reqs->firstWhere('id', $pr3Id)['status']);

        // Admin payouts API & HTML render the approval queue
        $this->actingAs($admin)->getJson('/api/admin/payout-requests')
            ->assertOk()
            ->assertJsonPath('counts.paid', 2)
            ->assertJsonPath('counts.rejected', 1);

        $adminHtml = $this->actingAs($admin)->get('/')->getContent();
        $this->assertStringContainsString('id="admin-sec-payouts"', $adminHtml);
        $this->assertStringContainsString('id="admin-process-payout-modal"', $adminHtml);
    }

    public function test_driver_can_settle_negative_company_due_via_manual_upi_and_gateway(): void
    {
        $driver = Driver::create([
            'name' => 'Karan Mehta',
            'phone' => '9822001122',
            'vehicle_model' => 'Innova Crysta',
            'vehicle_plate' => 'MH12ZZ8899',
            'fleet_category' => 'suv',
            'wallet_balance' => 0,
            'is_online' => true,
            'is_verified' => true,
        ]);
        $driverUser = User::factory()->create([
            'role' => 'driver',
            'name' => 'Karan Mehta',
            'phone' => '9822001122',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        // Driver collects ₹1,400 on a trip where winning bid was ₹1,000 -> wallet goes to -₹400
        $booking = Booking::create([
            'booking_reference' => 'ICW-DUE-7701',
            'service_type' => 'cab',
            'trip_type' => 'oneway',
            'route_summary' => 'Pune → Mumbai',
            'pickup_location' => 'Kothrud',
            'drop_location' => 'Andheri',
            'customer_name' => 'Neha Kulkarni',
            'customer_phone' => '9876512345',
            'passengers' => [['name' => 'Neha Kulkarni']],
            'selected_units' => ['suv'],
            'base_fare' => 1400,
            'toll_fare' => 0,
            'addon_fare' => 0,
            'discount_fare' => 0,
            'total_amount' => 1400,
            'payment_mode' => 'cash',
            'payment_status' => 'driver_collect',
            'payment_collection_by' => 'driver',
            'status' => 'Started',
            'assigned_driver_id' => $driver->id,
            'driver_payout_amount' => 1000.0,
            'start_otp' => '4321',
        ]);

        $this->actingAs($driverUser)
            ->postJson("/api/driver/trips/{$booking->id}/complete", ['collected_by_driver' => true])
            ->assertOk()
            ->assertJsonPath('net_wallet_delta', -400)
            ->assertJsonPath('wallet_balance', -400);

        $this->actingAs($driverUser)->getJson('/api/driver/payment-history')
            ->assertOk()
            ->assertJsonPath('summary.wallet_balance', -400)
            ->assertJsonPath('summary.company_due_amount', 400);

        // 1. Driver submits Manual UPI UTR for ₹250, Admin rejects -> wallet remains -₹400
        $rejectSub = $this->actingAs($driverUser)->postJson('/api/driver/wallet/due-settlement', [
            'amount' => 250,
            'payment_channel' => 'upi_manual',
            'upi_utr' => 'BADUTR000111',
        ])->assertStatus(201);
        $badId = (int) $rejectSub->json('settlement.id');

        $this->actingAs($admin)->postJson("/api/admin/due-settlements/{$badId}/process", [
            'action' => 'reject',
            'admin_notes' => 'UTR not found',
        ])->assertOk()->assertJsonPath('due_settlement.status', 'rejected');
        $this->assertEquals(-400.0, (float) $driver->fresh()->wallet_balance);

        // 2. Driver submits valid Manual UPI UTR for ₹250, Admin approves -> wallet becomes -₹150
        $upiSub = $this->actingAs($driverUser)->postJson('/api/driver/wallet/due-settlement', [
            'amount' => 250,
            'payment_channel' => 'upi_manual',
            'upi_utr' => '432198765432',
        ])->assertStatus(201);
        $upiId = (int) $upiSub->json('settlement.id');

        $this->actingAs($admin)->postJson("/api/admin/due-settlements/{$upiId}/process", [
            'action' => 'approve',
        ])->assertOk()->assertJsonPath('due_settlement.status', 'paid');
        $this->assertEquals(-150.0, (float) $driver->fresh()->wallet_balance);

        // 3. Remaining ₹150 settled via Razorpay Payment Gateway -> wallet becomes ₹0
        PlatformSetting::set('payment_gateway', 'razorpay');
        PlatformSetting::set('razorpay_key_id', 'rzp_test_due_123');
        PlatformSetting::set('razorpay_key_secret', 'rzp_secret_due_456');

        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_due_999',
                'amount' => 15000,
                'currency' => 'INR',
            ], 200),
        ]);

        $gwSub = $this->actingAs($driverUser)->postJson('/api/driver/wallet/due-settlement', [
            'amount' => 150,
            'payment_channel' => 'gateway',
        ])->assertStatus(201)
            ->assertJsonPath('razorpay_order.order_id', 'order_due_999');

        $gwId = (int) $gwSub->json('settlement.id');
        $payId = 'pay_due_888';
        $sig = hash_hmac('sha256', 'order_due_999|'.$payId, 'rzp_secret_due_456');

        $this->actingAs($driverUser)->postJson("/api/driver/wallet/due-settlement/{$gwId}/verify-gateway", [
            'razorpay_order_id' => 'order_due_999',
            'razorpay_payment_id' => $payId,
            'razorpay_signature' => $sig,
        ])->assertOk()
            ->assertJsonPath('wallet_balance', 0);

        $this->assertEquals(0.0, (float) $driver->fresh()->wallet_balance);
    }
}
