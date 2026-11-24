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
}
