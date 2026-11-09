<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PlatformSetting;

class PlatformSettingAdminController extends Controller
{
    public function getSettings()
    {
        return response()->json([
            'success' => true,
            'settings' => [
                'privacy_policy_url' => PlatformSetting::get('privacy_policy_url', '/privacy-policy'),
                'terms_conditions_url' => PlatformSetting::get('terms_conditions_url', '/terms-conditions'),
            ]
        ]);
    }

    public function updatePolicyUrls(Request $request)
    {
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin role required.'
            ], 403);
        }

        $validated = $request->validate([
            'privacy_policy_url' => 'required|string|max:255',
            'terms_conditions_url' => 'required|string|max:255',
        ]);

        PlatformSetting::set('privacy_policy_url', $validated['privacy_policy_url']);
        PlatformSetting::set('terms_conditions_url', $validated['terms_conditions_url']);

        return response()->json([
            'success' => true,
            'message' => 'Policy and T&Cs URLs updated successfully!',
            'settings' => [
                'privacy_policy_url' => $validated['privacy_policy_url'],
                'terms_conditions_url' => $validated['terms_conditions_url'],
            ]
        ]);
    }
}
