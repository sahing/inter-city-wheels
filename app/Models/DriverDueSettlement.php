<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverDueSettlement extends Model
{
    use HasFactory;

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    public const CHANNEL_GATEWAY = 'gateway';

    public const CHANNEL_UPI_MANUAL = 'upi_manual';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function walletTransaction()
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }
}
