<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverPayoutRequest;
use App\Models\WalletTransaction;
use App\Services\DriverPayoutGatewayService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DriverPayoutAdminController extends Controller
{
    public function __construct(
        private readonly DriverPayoutGatewayService $gateway,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request)
    {
        $status = trim((string) $request->query('status', 'all'));
        $query = DriverPayoutRequest::with(['driver', 'processor'])->latest('id');

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $requests = $query->limit(150)->get()->map(function (DriverPayoutRequest $pr) {
            return [
                'id' => $pr->id,
                'payout_reference' => $pr->payout_reference,
                'driver_id' => $pr->driver_id,
                'driver_name' => $pr->driver?->name ?? 'Driver',
                'driver_phone' => $pr->driver?->phone ?? '',
                'amount' => round((float) $pr->amount, 2),
                'payout_method' => $pr->payout_method,
                'payout_destination' => $pr->payout_destination,
                'destination_summary' => $pr->destinationSummary(),
                'status' => $pr->status,
                'payout_mode' => $pr->payout_mode,
                'gateway_provider' => $pr->gateway_provider,
                'transaction_reference' => $pr->transaction_reference,
                'admin_notes' => $pr->admin_notes,
                'processed_by_name' => $pr->processor?->name,
                'requested_at' => $pr->requested_at?->toIso8601String(),
                'requested_at_label' => $pr->requested_at?->format('d M Y, h:i A') ?? $pr->created_at?->format('d M Y, h:i A'),
                'processed_at' => $pr->processed_at?->toIso8601String(),
                'processed_at_label' => $pr->processed_at?->format('d M Y, h:i A'),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'gateway_ready' => $this->gateway->isReady(),
            'counts' => [
                'all' => DriverPayoutRequest::count(),
                'pending' => DriverPayoutRequest::where('status', DriverPayoutRequest::STATUS_PENDING)->count(),
                'paid' => DriverPayoutRequest::where('status', DriverPayoutRequest::STATUS_PAID)->count(),
                'rejected' => DriverPayoutRequest::where('status', DriverPayoutRequest::STATUS_REJECTED)->count(),
            ],
            'payout_requests' => $requests,
        ]);
    }

    public function process(Request $request, int $id)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:approve_manual,approve_gateway,reject',
            'transaction_reference' => 'nullable|string|max:120',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $action = $validated['action'];
        $txnRef = trim((string) ($validated['transaction_reference'] ?? ''));
        $adminNotes = trim((string) ($validated['admin_notes'] ?? ''));

        if ($action === 'approve_manual' && $txnRef === '') {
            return response()->json([
                'success' => false,
                'message' => 'Enter the UTR / IMPS / Bank Reference number for Manual payout approval.',
            ], 422);
        }

        $payoutRequest = DriverPayoutRequest::with('driver')->findOrFail($id);

        if ($payoutRequest->status !== DriverPayoutRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => "Payout request {$payoutRequest->payout_reference} has already been processed ({$payoutRequest->status}).",
            ], 422);
        }

        $gatewayResult = null;
        if ($action === 'approve_gateway') {
            try {
                $gatewayResult = $this->gateway->sendPayout($payoutRequest);
            } catch (Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gateway API payout failed: '.$e->getMessage(),
                ], 422);
            }
        }

        $updated = DB::transaction(function () use ($payoutRequest, $action, $txnRef, $adminNotes, $gatewayResult, $request) {
            $locked = DriverPayoutRequest::whereKey($payoutRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DriverPayoutRequest::STATUS_PENDING) {
                abort(422, 'Payout request was already processed.');
            }

            if ($action === 'reject') {
                $driver = Driver::whereKey($locked->driver_id)->lockForUpdate()->firstOrFail();
                $driver->wallet_balance = round((float) $driver->wallet_balance + (float) $locked->amount, 2);
                $driver->save();

                $reasonText = $adminNotes !== '' ? $adminNotes : 'Rejected by Admin';
                $refundTx = WalletTransaction::create([
                    'driver_id' => $driver->id,
                    'booking_id' => null,
                    'type' => WalletTransaction::TYPE_CREDIT,
                    'reason' => WalletTransaction::REASON_ADJUSTMENT,
                    'amount' => (float) $locked->amount,
                    'balance_after' => $driver->wallet_balance,
                    'description' => "Refund for rejected payout {$locked->payout_reference}: {$reasonText}",
                ]);

                $locked->forceFill([
                    'status' => DriverPayoutRequest::STATUS_REJECTED,
                    'admin_notes' => $reasonText,
                    'refund_wallet_transaction_id' => $refundTx->id,
                    'processed_by' => $request->user()?->id,
                    'processed_at' => now(),
                ])->save();

                return $locked->fresh(['driver', 'processor']);
            }

            $isGateway = $action === 'approve_gateway';
            $effectiveRef = $isGateway
                ? (string) ($gatewayResult['transaction_reference'] ?? $gatewayResult['payout_id'] ?? '')
                : $txnRef;
            $provider = $isGateway
                ? (string) ($gatewayResult['provider'] ?? 'razorpayx')
                : ('manual_'.$locked->payout_method);

            $locked->forceFill([
                'status' => DriverPayoutRequest::STATUS_PAID,
                'payout_mode' => $isGateway ? DriverPayoutRequest::MODE_GATEWAY : DriverPayoutRequest::MODE_MANUAL,
                'gateway_provider' => $provider,
                'transaction_reference' => $effectiveRef,
                'gateway_response' => $isGateway ? ($gatewayResult['response'] ?? null) : null,
                'admin_notes' => $adminNotes !== '' ? $adminNotes : ($isGateway ? 'Paid automatically via Gateway API' : 'Paid manually by Admin'),
                'processed_by' => $request->user()?->id,
                'processed_at' => now(),
            ])->save();

            if ($locked->wallet_transaction_id) {
                $wt = WalletTransaction::whereKey($locked->wallet_transaction_id)->first();
                if ($wt) {
                    $modeLabel = $isGateway ? 'Gateway API' : 'Manual Transfer';
                    $wt->forceFill([
                        'description' => "Payout {$locked->payout_reference} paid via {$modeLabel} ({$locked->destinationSummary()}) • Ref: {$effectiveRef}",
                    ])->save();
                }
            }

            return $locked->fresh(['driver', 'processor']);
        });

        $this->notifications->payoutProcessed($updated);

        $msg = match ($action) {
            'approve_gateway' => "Payout {$updated->payout_reference} processed via Payment Gateway API (Ref: {$updated->transaction_reference}).",
            'approve_manual' => "Payout {$updated->payout_reference} approved & recorded via Manual mode (Ref: {$updated->transaction_reference}).",
            default => "Payout {$updated->payout_reference} rejected and ₹".number_format((float) $updated->amount).' refunded to driver wallet.',
        };

        return response()->json([
            'success' => true,
            'message' => $msg,
            'payout_request' => [
                'id' => $updated->id,
                'payout_reference' => $updated->payout_reference,
                'driver_id' => $updated->driver_id,
                'driver_name' => $updated->driver?->name,
                'amount' => round((float) $updated->amount, 2),
                'payout_method' => $updated->payout_method,
                'destination_summary' => $updated->destinationSummary(),
                'status' => $updated->status,
                'payout_mode' => $updated->payout_mode,
                'gateway_provider' => $updated->gateway_provider,
                'transaction_reference' => $updated->transaction_reference,
                'admin_notes' => $updated->admin_notes,
                'processed_at_label' => $updated->processed_at?->format('d M Y, h:i A'),
            ],
        ]);
    }
}
