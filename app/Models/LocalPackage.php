<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalPackage extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'duration_hours' => 'integer',
        'included_km' => 'integer',
        'sedan_fare' => 'float',
        'suv_fare' => 'float',
        'extra_km_rate' => 'float',
        'extra_hour_rate' => 'float',
        'is_active' => 'boolean',
    ];
}

