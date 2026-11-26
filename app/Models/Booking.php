<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $hidden = [
        'razorpay_signature',
    ];

    protected $guarded = [];

    protected $appends = ['cab_type'];

    protected $casts = [
        'passengers' => 'array',
        'selected_units' => 'array',
        'dynamic_fields_data' => 'array',
        'departure_time' => 'datetime',
        'is_beyond_region' => 'boolean',
        'base_fare' => 'float',
        'toll_fare' => 'float',
        'addon_fare' => 'float',
        'discount_fare' => 'float',
        'total_amount' => 'float',
        'advance_percentage' => 'float',
        'advance_amount' => 'float',
        'balance_amount' => 'float',
        'driver_payout_amount' => 'float',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    public function bids()
    {
        return $this->hasMany(DriverBid::class);
    }

    public function biddingSessions()
    {
        return $this->hasMany(BiddingSession::class);
    }

    /**
     * The auction win that produced this assignment, when it came from bidding.
     */
    public function assignedBid()
    {
        return $this->belongsTo(DriverBid::class, 'assigned_bid_id');
    }

    /**
     * The signed-in customer who owns this booking, when it was made with an
     * account. Null for guest bookings (P0-8).
     */
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Normalized cab type (e.g. SEDAN, SUV, INNOVA) for cab bookings.
     */
    public function getCabTypeAttribute(): ?string
    {
        if ($this->service_type !== 'cab') {
            return null;
        }

        $unit = is_array($this->selected_units) && isset($this->selected_units[0])
            ? trim((string) $this->selected_units[0])
            : '';

        $driver = $this->relationLoaded('driver') ? $this->getRelation('driver') : null;

        if ($unit !== '' && strcasecmp($unit, 'Admin Assigned') !== 0) {
            if ($driver && $unit === $driver->vehicle_plate && ! empty($driver->fleet_category)) {
                return strtoupper((string) $driver->fleet_category);
            }

            return strtoupper($unit);
        }

        if ($driver && ! empty($driver->fleet_category)) {
            return strtoupper((string) $driver->fleet_category);
        }

        return null;
    }

    /**
     * Whether $user may read this booking without a signed URL.
     */
    public function isOwnedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $this->customer_user_id !== null && (int) $this->customer_user_id === (int) $user->id;
    }

    /**
     * Phone numbers are stored as typed by the customer ('+91 99999 88888',
     * '9999988888', ...), so compare on digits only.
     */
    public function matchesPhone(string $phone): bool
    {
        $expected = preg_replace('/[^0-9]/', '', (string) $this->customer_phone) ?? '';
        $given = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if ($expected === '' || $given === '') {
            return false;
        }

        $expected = strlen($expected) > 10 ? substr($expected, -10) : $expected;
        $given = strlen($given) > 10 ? substr($given, -10) : $given;

        return hash_equals($expected, $given);
    }
}
