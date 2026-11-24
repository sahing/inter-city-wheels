<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverVehicle extends Model
{
    protected $fillable = [
        'driver_id',
        'user_id',
        'driver_name',
        'driver_phone',
        'vehicle_model',
        'vehicle_plate',
        'fleet_category',
        'seating_capacity',
        'berth_capacity',
        'coach_code',
        'fuel_type',
        'model_year',
        'rc_number',
        'status',
        'admin_notes',
        'is_active',
    ];

    protected $casts = [
        'seating_capacity' => 'integer',
        'berth_capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function seatLayouts()
    {
        return $this->hasMany(SeatLayout::class, 'coach_code', 'coach_code');
    }
}
