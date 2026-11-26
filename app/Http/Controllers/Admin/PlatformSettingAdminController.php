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
            'settings' => [
                'privacy_policy_url' => PlatformSetting::get('privacy_policy_url', '/privacy-policy'),
                'terms_conditions_url' => PlatformSetting::get('terms_conditions_url', '/terms-conditions'),
                'enable_schedules_and_berths' => PlatformSetting::get('enable_schedules_and_berths', '0'),
                'default_car_type_oneway' => PlatformSetting::get('default_car_type_oneway', ''),
                'default_car_type_roundtrip' => PlatformSetting::get('default_car_type_roundtrip', ''),
                'default_car_type_local' => PlatformSetting::get('default_car_type_local', ''),
                'default_car_type_airport' => PlatformSetting::get('default_car_type_airport', ''),
            ],
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
}
