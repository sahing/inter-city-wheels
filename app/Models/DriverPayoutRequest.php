<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverPayoutRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    public const MODE_MANUAL = 'manual';

    public const MODE_GATEWAY = 'gateway';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'payout_destination' => 'array',
        'gateway_response' => 'array',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function walletTransaction()
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }

    public function refundWalletTransaction()
    {
        return $this->belongsTo(WalletTransaction::class, 'refund_wallet_transaction_id');
    }

    public function destinationSummary(): string
    {
        $dest = $this->payout_destination ?? [];
        if ($this->payout_method === 'upi') {
            $upi = $dest['upi_id'] ?? '';
            $name = $dest['upi_name'] ?? '';

            return $upi !== ''
                ? ('UPI: '.$upi.($name !== '' ? " ({$name})" : ''))
                : 'UPI Account';
        }

        $bank = $dest['bank_name'] ?? '';
        $acct = (string) ($dest['bank_account_number'] ?? '');
        $last4 = strlen($acct) >= 4 ? substr($acct, -4) : $acct;
        $ifsc = $dest['bank_ifsc'] ?? '';

        if ($last4 !== '') {
            return trim(($bank !== '' ? $bank.' ' : 'Bank ').'•••• '.$last4.($ifsc !== '' ? " ({$ifsc})" : ''));
        }

        return 'Registered Bank Account';
    }
}
