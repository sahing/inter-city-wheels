<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DynamicFormField extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'is_price_field' => 'boolean',
        'price_amount' => 'float',
        'route_fare_id' => 'integer',
        'order_index' => 'integer',
    ];

    public function routeFare()
    {
        return $this->belongsTo(RouteFare::class, 'route_fare_id');
    }
}
