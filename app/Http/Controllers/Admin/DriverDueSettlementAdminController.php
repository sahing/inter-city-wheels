<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverDueSettlement;
use App\Models\WalletTransaction;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverDueSettlementAdminController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request)
    {
        $status = trim((string) $request->query('status', 'all'));
        $query = DriverDueSettlement::with(['driver', 'verifier'])->latest('id');

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $settlements = $query->limit(150)->get()->map(function (DriverDueSettlement $ds) {
            return [
                'id' => $ds->id,
                'settlement_reference' => $ds->settlement_reference,
                'driver_id' => $ds->driver_id,
                'driver_name' => $ds->driver?->name ?? 'Driver',
                'driver_phone' => $ds->driver?->phone ?? '',
                'driver_wallet_balance' => round((float) ($ds->driver?->wallet_balance ?? 0), 2),
                'amount' => round((float) $ds->amount, 2),
                'payment_channel' => $ds->payment_channel,
                'status' => $ds->status,
                'upi_utr' => $ds->upi_utr,
                'razorpay_payment_id' => $ds->razorpay_payment_id,
                'driver_notes' => $ds->driver_notes,
                'admin_notes' => $ds->admin_notes,
                'verified_by_name' => $ds->verifier?->name,
                'submitted_at' => $ds->submitted_at?->toIso8601String(),
                'submitted_at_label' => $ds->submitted_at?->format('d M Y, h:i A') ?? $ds->created_at?->format('d M Y, h:i A'),
                'verified_at' => $ds->verified_at?->toIso8601String(),
                'verified_at_label' => $ds->verified_at?->format('d M Y, h:i A'),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'counts' => [
                'all' => DriverDueSettlement::count(),
                'pending_verification' => DriverDueSettlement::where('status', DriverDueSettlement::STATUS_PENDING_VERIFICATION)->count(),
                'paid' => DriverDueSettlement::where('status', DriverDueSettlement::STATUS_PAID)->count(),
                'rejected' => DriverDueSettlement::where('status', DriverDueSettlement::STATUS_REJECTED)->count(),
            ],
            'due_settlements' => $settlements,
        ]);
    }

    public function process(Request $request, int $id)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $action = $validated['action'];
        $adminNotes = trim((string) ($validated['admin_notes'] ?? ''));

        $settlement = DriverDueSettlement::with('driver')->findOrFail($id);

        if ($settlement->status !== DriverDueSettlement::STATUS_PENDING_VERIFICATION) {
            return response()->json([
                'success' => false,
                'message' => "Settlement {$settlement->settlement_reference} has already been processed ({$settlement->status}).",
            ], 422);
        }

        $updated = DB::transaction(function () use ($settlement, $action, $adminNotes, $request) {
            $locked = DriverDueSettlement::whereKey($settlement->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DriverDueSettlement::STATUS_PENDING_VERIFICATION) {
                abort(422, 'Settlement was already processed.');
            }

            if ($action === 'reject') {
                $reason = $adminNotes !== '' ? $adminNotes : 'UTR could not be verified by Admin';
                $locked->forceFill([
                    'status' => DriverDueSettlement::STATUS_REJECTED,
                    'admin_notes' => $reason,
                    'verified_by' => $request->user()?->id,
                    'verified_at' => now(),
                ])->save();

                return $locked->fresh(['driver', 'verifier']);
            }

            $driver = Driver::whereKey($locked->driver_id)->lockForUpdate()->firstOrFail();
            $driver->wallet_balance = round((float) $driver->wallet_balance + (float) $locked->amount, 2);
            $driver->save();

            $utrNote = $locked->upi_utr ? " (UTR {$locked->upi_utr})" : '';
            $tx = WalletTransaction::create([
                'driver_id' => $driver->id,
                'booking_id' => null,
                'type' => WalletTransaction::TYPE_CREDIT,
                'reason' => WalletTransaction::REASON_ADJUSTMENT,
                'amount' => (float) $locked->amount,
                'balance_after' => $driver->wallet_balance,
                'description' => "Company due settled via Manual UPI {$locked->settlement_reference}{$utrNote}",
            ]);

            $locked->forceFill([
                'status' => DriverDueSettlement::STATUS_PAID,
                'admin_notes' => $adminNotes !== '' ? $adminNotes : 'Verified & credited by Admin',
                'wallet_transaction_id' => $tx->id,
                'verified_by' => $request->user()?->id,
                'verified_at' => now(),
            ])->save();

            return $locked->fresh(['driver', 'verifier']);
        });

        $this->notifications->dueSettlementVerified($updated, $action === 'approve');

        return response()->json([
            'success' => true,
            'message' => $action === 'approve'
                ? "Company due settlement {$updated->settlement_reference} verified! ₹".number_format((float) $updated->amount, 2)." credited to {$updated->driver?->name}'s wallet."
                : "Company due settlement {$updated->settlement_reference} rejected.",
            'due_settlement' => $updated,
        ]);
    }
}
