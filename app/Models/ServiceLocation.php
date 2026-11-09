<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceLocation extends Model
{
    protected $fillable = [
        'name',
        'state',
        'latitude',
        'longitude',
        'airport_code',
        'has_airport_service',
        'is_active',
    ];

    protected $casts = [
        'has_airport_service' => 'boolean',
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];
}

