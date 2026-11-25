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
        'hits' => 'integer',
        'resolved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
