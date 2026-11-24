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
        'expires_at' => 'datetime',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
    ];

    /**
     * Whether the coupon can still be redeemed right now, ignoring the basket.
     * Kept next to the model so admin screens and the pricing service agree.
     */
    public function isRedeemableAt(?float $subtotal = null): bool
    {
        if (! $this->is_active) {
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
}
