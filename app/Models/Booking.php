<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'passengers' => 'array',
        'selected_units' => 'array',
        'dynamic_fields_data' => 'array',
        'departure_time' => 'datetime'
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    public function bids()
    {
        return $this->hasMany(DriverBid::class);
    }
}
