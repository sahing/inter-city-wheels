<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BerthRoute extends Model
{
    protected $fillable = [
        'from_city',
        'to_city',
        'operator_name',
        'coach_code',
        'vehicle_type',
        'departure_time',
        'arrival_time',
        'base_price',
        'total_berths',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'float',
        'total_berths' => 'integer',
        'is_active' => 'boolean',
    ];

    public function seatLayouts()
    {
        return $this->hasMany(SeatLayout::class, 'coach_code', 'coach_code');
    }
}
