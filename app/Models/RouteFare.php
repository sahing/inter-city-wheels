<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteFare extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'base_toll' => 'float',
        'sedan_fare' => 'float',
        'suv_fare' => 'float',
        'hatchback_fare' => 'float',
        'innova_fare' => 'float',
        'tempo_fare' => 'float',
        'luxury_fare' => 'float',
        'roundtrip_discount_pct' => 'float',
        'car_fares' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get custom or formula fare for specific car type.
     */
    public function getFareForCarType(string $carType, float $defaultRatePerKm = 12.5): float
    {
        $key = strtolower($carType);

        if ($key === 'sedan' && ! empty($this->sedan_fare)) {
            return (float) $this->sedan_fare;
        }
        if ($key === 'suv' && ! empty($this->suv_fare)) {
            return (float) $this->suv_fare;
        }
        if ($key === 'hatchback' && ! empty($this->hatchback_fare)) {
            return (float) $this->hatchback_fare;
        }
        if ($key === 'innova' && ! empty($this->innova_fare)) {
            return (float) $this->innova_fare;
        }
        if ($key === 'tempo' && ! empty($this->tempo_fare)) {
            return (float) $this->tempo_fare;
        }
        if ($key === 'luxury' && ! empty($this->luxury_fare)) {
            return (float) $this->luxury_fare;
        }

        if (is_array($this->car_fares) && isset($this->car_fares[$key])) {
            return (float) $this->car_fares[$key];
        }

        return round($this->distance_km * $defaultRatePerKm + (float) $this->base_toll);
    }
}
