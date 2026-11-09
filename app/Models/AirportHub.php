<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AirportHub extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'pickup_surcharge' => 'float',
        'drop_surcharge' => 'float',
        'sedan_base_fare' => 'float',
        'suv_base_fare' => 'float',
        'is_active' => 'boolean',
    ];
}

