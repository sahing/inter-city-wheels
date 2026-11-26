<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeoPlaceCache extends Model
{
    protected $table = 'geo_place_cache';

    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_beyond_region' => 'boolean',
        'service_location_id' => 'integer',
        'last_booking_id' => 'integer',
        'approved_by_user_id' => 'integer',
        'hits' => 'integer',
        'resolved_at' => 'datetime',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
    ];
}
