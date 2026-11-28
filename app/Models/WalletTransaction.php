<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only record of every movement in a driver's wallet (P1-3). The sum of
 * these rows must always equal drivers.wallet_balance.
 */
class WalletTransaction extends Model
{
    use HasFactory;

    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    public const REASON_TRIP_PAYOUT = 'trip_payout';

    public const REASON_WITHDRAWAL = 'withdrawal';

    public const REASON_ADJUSTMENT = 'adjustment';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
