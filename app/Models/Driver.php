<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function bids()
    {
        return $this->hasMany(DriverBid::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'assigned_driver_id');
    }
}
