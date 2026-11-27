<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverPayoutRequest;
use App\Models\WalletTransaction;

class DriverPaymentHistoryService
{
    private const LEGACY_DRIVER_SHARE = 0.90;

    /**
     * Build the driver's complete summary, trip-wise payment part records, and
     * enriched wallet transaction ledger.
     *
     * @return array{
     *   summary: array<string, mixed>,
     *   trip_records: array<int, array<string, mixed>>,
     *   transactions: array<int, array<string, mixed>>
     * }
     */
    public function buildForDriver(?Driver $driver, int $transactionLimit = 30): array
    {
        if (! $driver) {
            return [
                'summary' => [
                    'wallet_balance' => 0.0,
                    'total_driver_collected' => 0.0,
                    'total_driver_earnings' => 0.0,
                    'total_platform_received' => 0.0,
                    'total_wallet_credited' => 0.0,
                    'total_wallet_settled' => 0.0,
                    'completed_trips_count' => 0,
                    'trips_count' => 0,
                    'rating' => 4.9,
                ],
                'trip_records' => [],
                'transactions' => [],
                'payout_requests' => [],
                'payout_profile' => [
                    'preferred_payout_method' => 'upi',
                    'has_upi' => false,
                    'has_bank' => false,
                    'summary_label' => 'Not configured',
                ],
            ];
        }

        $bookings = Booking::with(['assignedBid', 'walletTransactions'])
            ->where('assigned_driver_id', $driver->id)
            ->where('status', '!=', 'Cancelled')
            ->latest('id')
            ->get();

        $transactions = WalletTransaction::with(['booking.assignedBid', 'booking.walletTransactions'])
            ->where('driver_id', $driver->id)
            ->latest('id')
            ->take($transactionLimit)
            ->get();

        $payoutRequests = DriverPayoutRequest::with('processor')
            ->where('driver_id', $driver->id)
            ->latest('id')
            ->take(20)
            ->get()
            ->map(fn (DriverPayoutRequest $pr) => [
                'id' => $pr->id,
                'payout_reference' => $pr->payout_reference,
                'amount' => round((float) $pr->amount, 2),
                'payout_method' => $pr->payout_method,
                'destination_summary' => $pr->destinationSummary(),
                'status' => $pr->status,
                'payout_mode' => $pr->payout_mode,
                'gateway_provider' => $pr->gateway_provider,
                'transaction_reference' => $pr->transaction_reference,
                'admin_notes' => $pr->admin_notes,
                'requested_at_label' => $pr->requested_at?->format('d M Y, h:i A') ?? $pr->created_at?->format('d M Y, h:i A'),
                'processed_at_label' => $pr->processed_at?->format('d M Y, h:i A'),
            ])
            ->values()
            ->all();

        $tripRecords = $bookings
            ->map(fn (Booking $b) => $this->buildTripBreakdown($b, (int) $driver->id))
            ->values()
            ->all();

        $completedRecords = array_values(array_filter(
            $tripRecords,
            static fn (array $r) => $r['status'] === 'Completed' || $r['driver_collected_amount'] > 0
        ));

        $totalCollected = round(array_sum(array_column($tripRecords, 'driver_collected_amount')), 2);
        $totalEarnings = round(array_sum(array_map(
            static fn (array $r) => $r['status'] === 'Completed' ? $r['driver_payout'] : 0.0,
            $tripRecords
        )), 2);
        $totalPlatformReceived = round(array_sum(array_column($completedRecords, 'platform_received_amount')), 2);
        $totalWalletCredited = round(array_sum(array_map(
            static fn (array $r) => $r['wallet_settled_amount'] > 0 ? $r['wallet_settled_amount'] : 0.0,
            $completedRecords
        )), 2);
        $totalWalletSettled = round(array_sum(array_map(
            static fn (array $r) => $r['wallet_settled_amount'] < 0 ? abs($r['wallet_settled_amount']) : 0.0,
            $completedRecords
        )), 2);

        $enrichedTransactions = $transactions->map(function (WalletTransaction $wt) use ($driver) {
            $booking = $wt->booking;
            $breakdown = $booking ? $this->buildTripBreakdown($booking, (int) $driver->id) : null;

            return [
                'id' => $wt->id,
                'created_at' => $wt->created_at?->toIso8601String(),
                'date_label' => $wt->created_at ? $wt->created_at->format('d M Y') : '—',
                'time_label' => $wt->created_at ? $wt->created_at->format('H:i') : '',
                'type' => $wt->type,
                'reason' => $wt->reason,
                'reason_label' => $this->reasonLabel($wt->reason, (float) $wt->amount),
                'amount' => round((float) $wt->amount, 2),
                'balance_after' => round((float) $wt->balance_after, 2),
                'description' => $wt->description ?: ($breakdown['clarification'] ?? 'Wallet settlement'),
                'booking_id' => $booking?->id,
                'booking_reference' => $booking?->booking_reference,
                'route_summary' => $booking?->route_summary,
                'trip_breakdown' => $breakdown,
            ];
        })->values()->all();

        return [
            'summary' => [
                'wallet_balance' => round((float) $driver->wallet_balance, 2),
                'total_driver_collected' => $totalCollected,
                'total_driver_earnings' => $totalEarnings,
                'total_platform_received' => $totalPlatformReceived,
                'total_wallet_credited' => $totalWalletCredited,
                'total_wallet_settled' => $totalWalletSettled,
                'completed_trips_count' => count(array_filter($tripRecords, static fn (array $r) => $r['status'] === 'Completed')),
                'trips_count' => (int) ($driver->trips_count ?? 0),
                'rating' => (float) ($driver->rating ?? 4.9),
            ],
            'trip_records' => $tripRecords,
            'transactions' => $enrichedTransactions,
            'payout_requests' => $payoutRequests,
            'payout_profile' => [
                'preferred_payout_method' => $driver->preferred_payout_method ?: 'upi',
                'has_upi' => $driver->hasUpiPayoutConfigured(),
                'has_bank' => $driver->hasBankPayoutConfigured(),
                'payout_upi_id' => $driver->payout_upi_id,
                'payout_upi_name' => $driver->payout_upi_name,
                'bank_account_holder' => $driver->bank_account_holder,
                'bank_name' => $driver->bank_name,
                'bank_account_number' => $driver->bank_account_number,
                'bank_ifsc' => $driver->bank_ifsc,
                'summary_label' => $driver->payoutSummaryLabel(),
            ],
        ];
    }

    /**
     * Build a structured trip-wise payment & collection part record.
     *
     * @return array<string, mixed>
     */
    public function buildTripBreakdown(Booking $booking, ?int $driverId = null): array
    {
        $totalFare = round((float) ($booking->total_amount ?? 0), 2);
        $assignedBid = $booking->relationLoaded('assignedBid') ? $booking->assignedBid : null;
        $biddingAmount = $assignedBid
            ? round((float) $assignedBid->bid_amount, 2)
            : ($booking->driver_payout_amount !== null ? round((float) $booking->driver_payout_amount, 2) : null);
        $isBidWon = $biddingAmount !== null;

        $driverPayout = $booking->driver_payout_amount !== null
            ? round((float) $booking->driver_payout_amount, 2)
            : ($biddingAmount ?? round($totalFare * self::LEGACY_DRIVER_SHARE, 2));

        $driverCollected = $booking->driver_collected_at !== null
            ? round((float) ($booking->driver_collected_amount ?? 0), 2)
            : 0.0;

        $platformReceived = $this->resolvePlatformReceived($booking, $totalFare, $driverCollected);
        $pendingCollection = $this->resolvePendingDriverCollection($booking);

        $walletTxs = $booking->relationLoaded('walletTransactions')
            ? $booking->walletTransactions->filter(function (WalletTransaction $wt) use ($driverId) {
                $matchesDriver = $driverId === null || (int) $wt->driver_id === $driverId;

                return $matchesDriver && in_array($wt->reason, [
                    WalletTransaction::REASON_TRIP_PAYOUT,
                    WalletTransaction::REASON_ADJUSTMENT,
                ], true);
            })
            : collect();

        if ($walletTxs->isNotEmpty()) {
            $walletNet = round($walletTxs->sum(function (WalletTransaction $wt) {
                return $wt->type === WalletTransaction::TYPE_CREDIT
                    ? (float) $wt->amount
                    : -(float) $wt->amount;
            }), 2);
            $walletTxType = $walletNet > 0 ? 'credit' : ($walletNet < 0 ? 'debit' : 'settled');
        } elseif ($booking->status === 'Completed') {
            $walletNet = round($driverPayout - $driverCollected, 2);
            $walletTxType = $walletNet > 0 ? 'credit' : ($walletNet < 0 ? 'debit' : 'settled');
        } else {
            $projectedCollection = $driverCollected > 0 ? $driverCollected : $pendingCollection;
            $walletNet = round($driverPayout - $projectedCollection, 2);
            $walletTxType = 'pending';
        }

        $bidLabel = $isBidWon ? 'winning bid' : 'payout (90% share)';
        $clarification = $this->buildClarification(
            status: (string) $booking->status,
            bidLabel: $bidLabel,
            driverPayout: $driverPayout,
            platformReceived: $platformReceived,
            driverCollected: $driverCollected,
            pendingCollection: $pendingCollection,
            walletNet: $walletNet
        );

        return [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'route_summary' => $booking->route_summary,
            'customer_name' => $booking->customer_name,
            'status' => $booking->status,
            'trip_date' => $booking->departure_time ? $booking->departure_time->format('d M Y, h:i A') : 'Scheduled',
            'total_fare' => $totalFare,
            'payment_mode' => $booking->payment_mode ?? 'full_online',
            'payment_mode_label' => $this->paymentModeLabel($booking),
            'payment_status' => $booking->payment_status ?? 'paid',
            'upi_utr' => $booking->upi_utr,
            'bidding_amount' => $biddingAmount,
            'is_bid_won' => $isBidWon,
            'driver_payout' => $driverPayout,
            'platform_received_amount' => $platformReceived,
            'driver_collected_amount' => $driverCollected,
            'driver_collected_at' => $booking->driver_collected_at?->toIso8601String(),
            'driver_collected_at_label' => $booking->driver_collected_at?->format('d M Y, h:i A'),
            'pending_driver_collection' => $pendingCollection,
            'wallet_settled_amount' => $walletNet,
            'wallet_transaction_type' => $walletTxType,
            'wallet_credited_part' => ($booking->status === 'Completed' && $walletNet > 0) ? $walletNet : 0.0,
            'wallet_debited_part' => ($booking->status === 'Completed' && $walletNet < 0) ? round(abs($walletNet), 2) : 0.0,
            'total_received_by_driver' => ($booking->status === 'Completed' || $driverCollected > 0)
                ? round($driverCollected + max(0.0, $walletNet) - max(0.0, -$walletNet), 2)
                : 0.0,
            'clarification' => $clarification,
        ];
    }

    public function formatCompletionDescription(
        Booking $booking,
        float $payout,
        float $collectDue,
        float $netWalletDelta,
        float $platformReceived
    ): string {
        $isBid = $booking->driver_payout_amount !== null || $booking->assigned_bid_id !== null;
        $bidLabel = $isBid ? 'winning bid' : 'payout';
        $ref = (string) $booking->booking_reference;

        if ($collectDue > 0) {
            $platformPart = $platformReceived > 0
                ? ' (Platform advance: ₹'.number_format($platformReceived, 2).')'
                : '';

            if ($netWalletDelta > 0) {
                return sprintf(
                    'Collected ₹%s from customer%s. %s ₹%s − ₹%s collected = +₹%s credited to wallet (%s)',
                    number_format($collectDue, 2),
                    $platformPart,
                    ucfirst($bidLabel),
                    number_format($payout, 2),
                    number_format($collectDue, 2),
                    number_format($netWalletDelta, 2),
                    $ref
                );
            }

            if ($netWalletDelta < 0) {
                return sprintf(
                    'Collected ₹%s from customer%s. %s ₹%s; ₹%s platform margin settled from wallet (%s)',
                    number_format($collectDue, 2),
                    $platformPart,
                    ucfirst($bidLabel),
                    number_format($payout, 2),
                    number_format(abs($netWalletDelta), 2),
                    $ref
                );
            }

            return sprintf(
                'Collected ₹%s from customer%s, matching %s ₹%s (₹0 wallet adjustment) (%s)',
                number_format($collectDue, 2),
                $platformPart,
                $bidLabel,
                number_format($payout, 2),
                $ref
            );
        }

        return sprintf(
            'Trip %s ₹%s credited to wallet (%s, customer prepaid ₹%s to platform)',
            $bidLabel,
            number_format($payout, 2),
            $ref,
            number_format($platformReceived > 0 ? $platformReceived : (float) $booking->total_amount, 2)
        );
    }

    private function resolvePlatformReceived(Booking $booking, float $totalFare, float $driverCollected): float
    {
        if ($booking->payment_status === 'paid') {
            return $driverCollected > 0
                ? round(max(0.0, $totalFare - $driverCollected), 2)
                : $totalFare;
        }

        if ($booking->payment_status === 'partial_paid') {
            return round((float) ($booking->advance_amount ?? 0), 2);
        }

        return 0.0;
    }

    private function resolvePendingDriverCollection(Booking $booking): float
    {
        if ($booking->driver_collected_at !== null || $booking->status === 'Cancelled') {
            return 0.0;
        }

        if ($booking->payment_mode === 'part_pay' && (float) $booking->balance_amount > 0) {
            return round((float) $booking->balance_amount, 2);
        }

        if (
            $booking->payment_collection_by === 'driver'
            || in_array($booking->payment_status, ['pay_on_pickup', 'driver_collect', 'upi_rejected', 'pending_upi_verification', 'awaiting_upi_utr'], true)
        ) {
            return round((float) ($booking->balance_amount > 0 ? $booking->balance_amount : $booking->total_amount), 2);
        }

        return 0.0;
    }

    private function buildClarification(
        string $status,
        string $bidLabel,
        float $driverPayout,
        float $platformReceived,
        float $driverCollected,
        float $pendingCollection,
        float $walletNet
    ): string {
        if ($status === 'Completed' || $driverCollected > 0) {
            if ($driverCollected > 0) {
                $platformClause = $platformReceived > 0
                    ? ' Platform received ₹'.number_format($platformReceived).' advance online.'
                    : '';

                if ($walletNet > 0) {
                    return sprintf(
                        'You collected ₹%s from customer.%s Your %s is ₹%s, so +₹%s balance was credited to your wallet (Total received: ₹%s).',
                        number_format($driverCollected),
                        $platformClause,
                        $bidLabel,
                        number_format($driverPayout),
                        number_format($walletNet),
                        number_format($driverPayout)
                    );
                }

                if ($walletNet < 0) {
                    return sprintf(
                        'You collected ₹%s from customer.%s Your %s is ₹%s, so ₹%s excess platform margin was settled from your wallet (Net retained: ₹%s).',
                        number_format($driverCollected),
                        $platformClause,
                        $bidLabel,
                        number_format($driverPayout),
                        number_format(abs($walletNet)),
                        number_format($driverPayout)
                    );
                }

                return sprintf(
                    'You collected ₹%s from customer.%s This exactly equals your %s of ₹%s (₹0 wallet adjustment).',
                    number_format($driverCollected),
                    $platformClause,
                    $bidLabel,
                    number_format($driverPayout)
                );
            }

            return sprintf(
                'Customer prepaid ₹%s to platform. Your %s of ₹%s was credited in full to your wallet.',
                number_format($platformReceived),
                $bidLabel,
                number_format($driverPayout)
            );
        }

        if ($pendingCollection > 0) {
            $platformClause = $platformReceived > 0
                ? ' (Platform advance: ₹'.number_format($platformReceived).')'
                : '';

            return sprintf(
                'Collect ₹%s from customer on completion%s. Your %s is ₹%s.',
                number_format($pendingCollection),
                $platformClause,
                $bidLabel,
                number_format($driverPayout)
            );
        }

        return sprintf(
            'Customer prepaid ₹%s to platform. Full %s of ₹%s credits to your wallet upon trip completion.',
            number_format($platformReceived),
            $bidLabel,
            number_format($driverPayout)
        );
    }

    private function reasonLabel(?string $reason, float $amount): string
    {
        return match ($reason) {
            WalletTransaction::REASON_TRIP_PAYOUT => $amount == 0.0
                ? 'Trip Collection Settled (Exact Match)'
                : 'Trip Payout Settlement',
            WalletTransaction::REASON_ADJUSTMENT => 'Platform Margin Settlement',
            WalletTransaction::REASON_WITHDRAWAL => 'Bank IMPS Withdrawal',
            default => ucwords(str_replace('_', ' ', $reason ?? 'transaction')),
        };
    }

    private function paymentModeLabel(Booking $booking): string
    {
        return match ($booking->payment_mode) {
            'part_pay' => 'Partial Payment ('.((float) ($booking->advance_percentage ?: 20)).'% Adv + Balance)',
            'cash' => 'Pay Driver on Completion',
            'full_upi' => $booking->payment_collection_by === 'driver' ? 'Driver Collection (UPI/Cash)' : 'Full Manual UPI',
            default => $booking->payment_gateway === 'upi_manual' ? 'Full Payment (Manual UPI)' : 'Full Payment (Online)',
        };
    }
}
