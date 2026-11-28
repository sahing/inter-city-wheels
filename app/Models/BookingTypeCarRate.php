<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTypeCarRate extends Model
{
    use HasFactory;

    public const BOOKING_TYPES = [
        'oneway',
        'roundtrip',
        'local',
        'airport_pickup',
        'airport_drop',
    ];

    protected $guarded = [];

    protected $casts = [
        'car_type_id' => 'integer',
        'base_fare' => 'float',
        'rate_per_km' => 'float',
        'extra_km_rate' => 'float',
        'extra_hour_rate' => 'float',
        'is_active' => 'boolean',
    ];

    public function carType(): BelongsTo
    {
        return $this->belongsTo(CarType::class, 'car_type_id');
    }
}
