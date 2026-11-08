<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeatLayout extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_ladies_quota' => 'boolean',
        'is_booked' => 'boolean'
    ];
}
