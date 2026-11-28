<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'wallet_balance' => 'float',
        'rating' => 'float',
        'is_verified' => 'boolean',
        'is_online' => 'boolean',
    ];

    public function bids()
    {
        return $this->hasMany(DriverBid::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'assigned_driver_id');
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class)->latest('id');
    }

    public function payoutRequests()
    {
        return $this->hasMany(DriverPayoutRequest::class)->latest('id');
    }

    public function dueSettlements()
    {
        return $this->hasMany(DriverDueSettlement::class)->latest('id');
    }

    public function companyDueAmount(): float
    {
        return round(max(0.0, -(float) ($this->wallet_balance ?? 0)), 2);
    }

    public function hasUpiPayoutConfigured(): bool
    {
        return trim((string) ($this->payout_upi_id ?? '')) !== '';
    }

    public function hasBankPayoutConfigured(): bool
    {
        return trim((string) ($this->bank_account_holder ?? '')) !== ''
            && trim((string) ($this->bank_name ?? '')) !== ''
            && trim((string) ($this->bank_account_number ?? '')) !== ''
            && trim((string) ($this->bank_ifsc ?? '')) !== '';
    }

    public function payoutDestinationFor(string $method): ?array
    {
        if ($method === 'upi' && $this->hasUpiPayoutConfigured()) {
            return [
                'upi_id' => trim((string) $this->payout_upi_id),
                'upi_name' => trim((string) ($this->payout_upi_name ?: $this->name)),
            ];
        }

        if ($method === 'bank' && $this->hasBankPayoutConfigured()) {
            return [
                'bank_account_holder' => trim((string) $this->bank_account_holder),
                'bank_name' => trim((string) $this->bank_name),
                'bank_account_number' => trim((string) $this->bank_account_number),
                'bank_ifsc' => strtoupper(trim((string) $this->bank_ifsc)),
            ];
        }

        return null;
    }

    public function payoutSummaryLabel(): string
    {
        $pref = $this->preferred_payout_method ?: 'upi';
        if ($pref === 'upi' && $this->hasUpiPayoutConfigured()) {
            return 'UPI: '.$this->payout_upi_id.($this->payout_upi_name ? ' ('.$this->payout_upi_name.')' : '');
        }
        if ($this->hasBankPayoutConfigured()) {
            $acct = (string) $this->bank_account_number;
            $last4 = strlen($acct) >= 4 ? substr($acct, -4) : $acct;

            return $this->bank_name.' •••• '.$last4.' ('.$this->bank_ifsc.')';
        }
        if ($this->hasUpiPayoutConfigured()) {
            return 'UPI: '.$this->payout_upi_id;
        }

        return 'Not configured — add UPI or Bank details in Driver Profile';
    }
}
