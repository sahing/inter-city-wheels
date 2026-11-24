<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverBid extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'bid_amount' => 'float',
        'counter_amount' => 'float',
        'is_winning' => 'boolean',
        'flagged' => 'boolean',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function session()
    {
        return $this->belongsTo(BiddingSession::class, 'session_id');
    }
}
