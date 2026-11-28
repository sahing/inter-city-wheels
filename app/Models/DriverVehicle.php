<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverVehicle extends Model
{
    /**
     * Amenities an admin can attach to a car. The seeder, the admin vehicle
     * modal and the driver listing form all share this one catalogue so a
     * stored label always matches a label the UI offers.
     */
    public const FEATURES = [
        'Air Conditioning',
        'GPS Navigation',
        'Music System',
        'USB Charging',
        'Fast Charging',
        'Child Seat',
        'First Aid Kit',
        'Wi-Fi Hotspot',
        'Sunroof',
        'Leather Seats',
        'Dash Cam',
        'Cooler Box',
        'Reading Lights',
        'Oxygen Kit',
        'Pet Friendly',
        'Wheelchair Accessible',
        'Toll Fastag',
        'Roof Carrier',
        'Extra Luggage Space',
        'Blankets & Pillows',
    ];

    protected $fillable = [
        'driver_id',
        'user_id',
        'driver_name',
        'driver_phone',
        'vehicle_model',
        'vehicle_plate',
        'fleet_category',
        'seating_capacity',
        'berth_capacity',
        'coach_code',
        'fuel_type',
        'model_year',
        'rc_number',
        'features',
        'status',
        'admin_notes',
        'is_active',
    ];

    protected $casts = [
        'seating_capacity' => 'integer',
        'berth_capacity' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Stored feature labels as a clean, de-duplicated list.
     */
    public function featureLabels(): array
    {
        $features = $this->features;

        if (is_string($features)) {
            $features = json_decode($features, true) ?: [];
        }

        if (! is_array($features)) {
            return [];
        }

        $labels = [];

        foreach ($features as $feature) {
            if (! is_string($feature)) {
                continue;
            }

            $label = trim($feature);

            if ($label !== '' && ! in_array($label, $labels, true)) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function seatLayouts()
    {
        return $this->hasMany(SeatLayout::class, 'coach_code', 'coach_code');
    }
}
