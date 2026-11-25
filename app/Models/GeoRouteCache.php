<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeoRouteCache extends Model
{
    protected $table = 'geo_route_cache';

    protected $guarded = [];

    protected $casts = [
        'distance_km' => 'integer',
        'duration_min' => 'integer',
        'base_toll' => 'float',
        'from_lat' => 'float',
        'from_lng' => 'float',
        'to_lat' => 'float',
        'to_lng' => 'float',
        'hits' => 'integer',
        'resolved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
