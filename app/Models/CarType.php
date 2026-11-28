<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarType extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'rate_per_km' => 'float',
        'extra_km_rate' => 'float',
        'base_fare_min' => 'float',
        'order_index' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope for active car types ordered by order_index.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('order_index');
    }
}
