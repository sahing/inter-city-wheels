<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_spend' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'booking_types' => 'array',
        'car_types' => 'array',
        'route_fare_ids' => 'array',
        'target_user_ids' => 'array',
        'target_driver_ids' => 'array',
        'per_user_limit' => 'integer',
        'auto_apply' => 'boolean',
        'show_on_checkout' => 'boolean',
    ];

    /**
     * Whether the coupon can still be redeemed right now, ignoring trip context.
     * Kept next to the model so admin screens and the pricing service agree.
     */
    public function isRedeemableAt(?float $subtotal = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        if ($subtotal !== null && $subtotal < (float) $this->min_spend) {
            return false;
        }

        return true;
    }

    public function remainingUses(): ?int
    {
        return $this->usage_limit === null
            ? null
            : max(0, $this->usage_limit - $this->used_count);
    }

    /**
     * Human-readable summary badges of configured conditions for admin & checkout UI.
     *
     * @return list<string>
     */
    public function conditionSummaryList(): array
    {
        $badges = [];

        if ($this->starts_at !== null || $this->expires_at !== null) {
            $from = $this->starts_at?->format('d M Y');
            $to = $this->expires_at?->format('d M Y');
            if ($from && $to) {
                $badges[] = "Dates: {$from} - {$to}";
            } elseif ($from) {
                $badges[] = "From: {$from}";
            } elseif ($to) {
                $badges[] = "Until: {$to}";
            }
        }

        $bTypes = array_values(array_filter((array) ($this->booking_types ?? [])));
        if ($bTypes !== []) {
            $badges[] = 'Booking: '.implode(', ', array_map('ucfirst', $bTypes));
        }

        $cTypes = array_values(array_filter((array) ($this->car_types ?? [])));
        if ($cTypes !== []) {
            $badges[] = 'Cars: '.implode(', ', array_map('strtoupper', $cTypes));
        }

        $routes = array_values(array_filter((array) ($this->route_fare_ids ?? [])));
        if ($routes !== []) {
            $badges[] = count($routes).' Route(s)';
        }

        $users = array_values(array_filter((array) ($this->target_user_ids ?? [])));
        if ($users !== []) {
            $badges[] = count($users).' Customer(s)';
        }

        $drivers = array_values(array_filter((array) ($this->target_driver_ids ?? [])));
        if ($drivers !== []) {
            $badges[] = count($drivers).' Driver(s)';
        }

        if ($this->per_user_limit !== null) {
            $badges[] = "Max {$this->per_user_limit}/user";
        }

        if ((float) ($this->min_spend ?? 0) > 0) {
            $badges[] = 'Min ₹'.number_format((float) $this->min_spend, 0);
        }

        return $badges;
    }
}
