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
        'additional_amount' => 'float',
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
     * Whether this route specifies an explicit fixed fare override for $carType.
     */
    public function hasCustomFareForCarType(string $carType): bool
    {
        $key = strtolower($carType);

        if (in_array($key, ['sedan', 'suv', 'hatchback', 'innova', 'tempo', 'luxury'], true)) {
            $col = $key.'_fare';
            if (! empty($this->{$col}) && (float) $this->{$col} > 0) {
                return true;
            }
        }

        return is_array($this->car_fares) && isset($this->car_fares[$key]) && (float) $this->car_fares[$key] > 0;
    }

    /**
     * Get custom override or formula fare (Base Fare + Distance * Rate + Highway Toll + Route Additional).
     */
    public function getFareForCarType(string $carType, float $defaultRatePerKm = 12.5, float $fixedBaseFare = 0.0, bool $includeAdditional = true): float
    {
        $key = strtolower($carType);
        $additional = $includeAdditional ? (float) ($this->additional_amount ?? 0) : 0.0;

        if ($key === 'sedan' && ! empty($this->sedan_fare)) {
            return round((float) $this->sedan_fare + $additional);
        }
        if ($key === 'suv' && ! empty($this->suv_fare)) {
            return round((float) $this->suv_fare + $additional);
        }
        if ($key === 'hatchback' && ! empty($this->hatchback_fare)) {
            return round((float) $this->hatchback_fare + $additional);
        }
        if ($key === 'innova' && ! empty($this->innova_fare)) {
            return round((float) $this->innova_fare + $additional);
        }
        if ($key === 'tempo' && ! empty($this->tempo_fare)) {
            return round((float) $this->tempo_fare + $additional);
        }
        if ($key === 'luxury' && ! empty($this->luxury_fare)) {
            return round((float) $this->luxury_fare + $additional);
        }

        if (is_array($this->car_fares) && isset($this->car_fares[$key]) && (float) $this->car_fares[$key] > 0) {
            return round((float) $this->car_fares[$key] + $additional);
        }

        return round($fixedBaseFare + ($this->distance_km * $defaultRatePerKm) + (float) $this->base_toll + $additional);
    }
}
