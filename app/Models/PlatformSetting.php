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
}
