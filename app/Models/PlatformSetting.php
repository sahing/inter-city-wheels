<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, ?string $value): static
    {
        return static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function isSchedulesAndBerthsEnabled(): bool
    {
        return static::get('enable_schedules_and_berths', '0') === '1';
    }

    /**
     * Default pricing, night stay, hours, and booking condition values.
     *
     * @return array<string, string>
     */
    public static function pricingDefaults(): array
    {
        return [
            'addon_pet_friendly_rate' => '350',
            'addon_night_allowance_rate' => '400',
            'addon_luggage_carrier_rate' => '200',
            'night_stay_start_hour' => '23:00',
            'night_stay_end_hour' => '06:00',
            'roundtrip_min_km_per_day' => '250',
            'driver_day_allowance' => '0',
            'free_waiting_minutes' => '45',
            'waiting_charge_per_hour' => '150',
            'free_cancellation_hours' => '1',
            'other_booking_conditions' => 'Highway tolls, state taxes & parking as applicable. Night stay applies during configured night hours.',
            'bidding_min_service_charge_pct' => '15',
            'bidding_max_service_charge_pct' => '75',
        ];
    }

    /**
     * Load all configurable pricing & booking condition settings with defaults.
     *
     * @return array<string, string>
     */
    public static function pricingSettings(): array
    {
        $defaults = static::pricingDefaults();
        $stored = static::whereIn('key', array_keys($defaults))->pluck('value', 'key')->all();

        $resolved = [];
        foreach ($defaults as $key => $default) {
            $val = $stored[$key] ?? null;
            $resolved[$key] = ($val !== null && $val !== '') ? (string) $val : $default;
        }

        return $resolved;
    }

    /**
     * Load all platform settings exposed to the admin & customer UI.
     *
     * @return array<string, string>
     */
    public static function allAdminSettings(): array
    {
        return array_merge([
            'privacy_policy_url' => static::get('privacy_policy_url', '/privacy-policy') ?? '/privacy-policy',
            'terms_conditions_url' => static::get('terms_conditions_url', '/terms-conditions') ?? '/terms-conditions',
            'enable_schedules_and_berths' => static::get('enable_schedules_and_berths', '0') ?? '0',
            'default_car_type_oneway' => static::get('default_car_type_oneway', '') ?? '',
            'default_car_type_roundtrip' => static::get('default_car_type_roundtrip', '') ?? '',
            'default_car_type_local' => static::get('default_car_type_local', '') ?? '',
            'default_car_type_airport' => static::get('default_car_type_airport', '') ?? '',
        ], static::pricingSettings());
    }
}
