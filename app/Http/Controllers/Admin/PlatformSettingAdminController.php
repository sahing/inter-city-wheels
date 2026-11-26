<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;

class PlatformSettingAdminController extends Controller
{
    public function getSettings()
    {
        return response()->json([
            'success' => true,
            'settings' => PlatformSetting::allAdminSettings(),
        ]);
    }

    public function updatePolicyUrls(Request $request)
    {
        if (! auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin role required.',
            ], 403);
        }

        $validated = $request->validate([
            'privacy_policy_url' => 'required|string|max:255',
            'terms_conditions_url' => 'required|string|max:255',
            'enable_schedules_and_berths' => 'nullable',
        ]);

        PlatformSetting::set('privacy_policy_url', $validated['privacy_policy_url']);
        PlatformSetting::set('terms_conditions_url', $validated['terms_conditions_url']);

        if ($request->has('enable_schedules_and_berths')) {
            $enabled = filter_var($request->input('enable_schedules_and_berths'), FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            PlatformSetting::set('enable_schedules_and_berths', $enabled);
        }

        return response()->json([
            'success' => true,
            'message' => 'Policy and T&Cs URLs updated successfully!',
            'settings' => [
                'privacy_policy_url' => $validated['privacy_policy_url'],
                'terms_conditions_url' => $validated['terms_conditions_url'],
                'enable_schedules_and_berths' => PlatformSetting::get('enable_schedules_and_berths', '0'),
                'default_car_type_oneway' => PlatformSetting::get('default_car_type_oneway', ''),
                'default_car_type_roundtrip' => PlatformSetting::get('default_car_type_roundtrip', ''),
                'default_car_type_local' => PlatformSetting::get('default_car_type_local', ''),
                'default_car_type_airport' => PlatformSetting::get('default_car_type_airport', ''),
            ],
        ]);
    }

    public function toggleFeature(Request $request)
    {
        if (! auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin role required.',
            ], 403);
        }

        $feature = $request->input('feature', 'schedules_and_berths');
        if ($feature === 'schedules_and_berths' || $request->has('enable_schedules_and_berths')) {
            if ($request->has('enabled')) {
                $newVal = filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            } elseif ($request->has('enable_schedules_and_berths')) {
                $newVal = filter_var($request->input('enable_schedules_and_berths'), FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            } else {
                $current = PlatformSetting::get('enable_schedules_and_berths', '0');
                $newVal = ($current === '1') ? '0' : '1';
            }

            PlatformSetting::set('enable_schedules_and_berths', $newVal);

            return response()->json([
                'success' => true,
                'message' => 'Schedules & Berths option turned '.($newVal === '1' ? 'ON' : 'OFF').' successfully!',
                'feature' => 'schedules_and_berths',
                'enabled' => $newVal === '1',
                'enable_schedules_and_berths' => $newVal,
                'settings' => [
                    'privacy_policy_url' => PlatformSetting::get('privacy_policy_url', '/privacy-policy'),
                    'terms_conditions_url' => PlatformSetting::get('terms_conditions_url', '/terms-conditions'),
                    'enable_schedules_and_berths' => $newVal,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unknown feature specified.',
        ], 400);
    }

    public function updateBookingTypeCarTypes(Request $request)
    {
        if (! auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin role required.',
            ], 403);
        }

        $validated = $request->validate([
            'oneway' => 'nullable|string|max:60',
            'roundtrip' => 'nullable|string|max:60',
            'local' => 'nullable|string|max:60',
            'airport' => 'nullable|string|max:60',
        ]);

        foreach (['oneway', 'roundtrip', 'local', 'airport'] as $type) {
            if ($request->has($type)) {
                $val = strtolower(trim((string) ($validated[$type] ?? '')));
                PlatformSetting::set("default_car_type_{$type}", $val);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking type car type preferences updated.',
            'settings' => [
                'default_car_type_oneway' => PlatformSetting::get('default_car_type_oneway', ''),
                'default_car_type_roundtrip' => PlatformSetting::get('default_car_type_roundtrip', ''),
                'default_car_type_local' => PlatformSetting::get('default_car_type_local', ''),
                'default_car_type_airport' => PlatformSetting::get('default_car_type_airport', ''),
            ],
        ]);
    }

    /**
     * Persist night stay rate, night hours, surcharges, and booking fare conditions.
     */
    public function updatePricingConditions(Request $request)
    {
        if (! auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin role required.',
            ], 403);
        }

        $validated = $request->validate([
            'addon_pet_friendly_rate' => 'required|numeric|min:0',
            'addon_night_allowance_rate' => 'required|numeric|min:0',
            'addon_luggage_carrier_rate' => 'required|numeric|min:0',
            'night_stay_start_hour' => 'required|string|max:10',
            'night_stay_end_hour' => 'required|string|max:10',
            'roundtrip_min_km_per_day' => 'required|integer|min:0|max:2000',
            'driver_day_allowance' => 'nullable|numeric|min:0',
            'free_waiting_minutes' => 'nullable|integer|min:0|max:480',
            'waiting_charge_per_hour' => 'nullable|numeric|min:0',
            'free_cancellation_hours' => 'nullable|integer|min:0|max:168',
            'other_booking_conditions' => 'nullable|string|max:1000',
        ]);

        $defaults = PlatformSetting::pricingDefaults();
        foreach (array_keys($defaults) as $key) {
            if (array_key_exists($key, $validated)) {
                $val = $validated[$key];
                PlatformSetting::set($key, $val === null ? '' : (string) $val);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Night stay rate, hours, and booking conditions saved!',
            'settings' => PlatformSetting::allAdminSettings(),
        ]);
    }
}
