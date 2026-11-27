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
            'bidding_min_service_charge_pct' => 'nullable|numeric|min:0|max:90',
            'bidding_max_service_charge_pct' => 'nullable|numeric|min:0|max:95|gte:bidding_min_service_charge_pct',
        ]);

        $defaults = PlatformSetting::pricingDefaults();
        foreach (array_keys($defaults) as $key) {
            if (array_key_exists($key, $validated)) {
                $val = $validated[$key];
                PlatformSetting::set($key, $val === null ? '' : (string) $val);
            }
        }

        if (array_key_exists('bidding_min_service_charge_pct', $validated) && $validated['bidding_min_service_charge_pct'] !== null) {
            PlatformSetting::set('bidding_admin_fee_pct', (string) $validated['bidding_min_service_charge_pct']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Night stay rate, hours, and booking conditions saved!',
            'settings' => PlatformSetting::allAdminSettings(),
        ]);
    }

    /**
     * Persist min and max bidding service charge percentages.
     */
    public function updateBiddingSettings(Request $request)
    {
        if (! auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin role required.',
            ], 403);
        }

        $validated = $request->validate([
            'bidding_min_service_charge_pct' => 'required|numeric|min:0|max:90',
            'bidding_max_service_charge_pct' => 'required|numeric|min:0|max:95|gte:bidding_min_service_charge_pct',
        ]);

        PlatformSetting::set('bidding_min_service_charge_pct', (string) $validated['bidding_min_service_charge_pct']);
        PlatformSetting::set('bidding_admin_fee_pct', (string) $validated['bidding_min_service_charge_pct']);
        PlatformSetting::set('bidding_max_service_charge_pct', (string) $validated['bidding_max_service_charge_pct']);

        return response()->json([
            'success' => true,
            'message' => 'Bidding service charge limits updated.',
            'settings' => PlatformSetting::allAdminSettings(),
        ]);
    }

    /**
     * Persist checkout payment option visibility, default selection, and advance percentage.
     */
    public function updatePaymentSettings(Request $request)
    {
        if (! auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin role required.',
            ], 403);
        }

        $validated = $request->validate([
            'payment_advance_pct' => 'required|numeric|min:1|max:99',
            'payment_show_full_online' => 'required|boolean',
            'payment_show_part_pay' => 'required|boolean',
            'payment_show_full_upi' => 'required|boolean',
            'payment_show_cash' => 'required|boolean',
            'payment_default_option' => 'nullable|string|in:full_online,part_pay,full_upi,cash',
            'upi_id' => 'nullable|string|max:120',
            'upi_payee_name' => 'nullable|string|max:120',
            'upi_instructions' => 'nullable|string|max:500',
            'payment_gateway' => 'nullable|string|in:manual,razorpay',
            'razorpay_mode' => 'nullable|string|in:test,live',
            'razorpay_key_id' => 'nullable|string|max:120',
            'razorpay_key_secret' => 'nullable|string|max:160',
            'razorpay_display_name' => 'nullable|string|max:80',
        ]);

        $enabledMap = [
            'full_online' => (bool) $validated['payment_show_full_online'],
            'part_pay' => (bool) $validated['payment_show_part_pay'],
            'full_upi' => (bool) $validated['payment_show_full_upi'],
            'cash' => (bool) $validated['payment_show_cash'],
        ];

        $enabledKeys = array_keys(array_filter($enabledMap));
        if ($enabledKeys === []) {
            return response()->json([
                'success' => false,
                'message' => 'Enable at least one checkout payment option.',
            ], 422);
        }

        $gateway = $validated['payment_gateway'] ?? PlatformSetting::get('payment_gateway', 'manual') ?? 'manual';
        $keyIdInput = $request->has('razorpay_key_id')
            ? trim((string) ($validated['razorpay_key_id'] ?? ''))
            : trim((string) (PlatformSetting::get('razorpay_key_id', '') ?? ''));
        $newSecretInput = trim((string) ($validated['razorpay_key_secret'] ?? ''));
        $existingSecret = trim((string) (PlatformSetting::get('razorpay_key_secret', '') ?? ''));

        if ($gateway === 'razorpay' && ($keyIdInput === '' || ($newSecretInput === '' && $existingSecret === ''))) {
            return response()->json([
                'success' => false,
                'message' => 'Enter both Razorpay Key ID and Key Secret before enabling Razorpay.',
            ], 422);
        }

        $defaultOption = $validated['payment_default_option'] ?? $enabledKeys[0];
        if (! in_array($defaultOption, $enabledKeys, true)) {
            $defaultOption = $enabledKeys[0];
        }

        $advPct = (float) $validated['payment_advance_pct'];
        $formattedPct = fmod($advPct, 1.0) === 0.0
            ? (string) (int) $advPct
            : rtrim(rtrim(number_format($advPct, 2, '.', ''), '0'), '.');

        PlatformSetting::set('payment_advance_pct', $formattedPct);
        PlatformSetting::set('payment_show_full_online', $enabledMap['full_online'] ? '1' : '0');
        PlatformSetting::set('payment_show_part_pay', $enabledMap['part_pay'] ? '1' : '0');
        PlatformSetting::set('payment_show_full_upi', $enabledMap['full_upi'] ? '1' : '0');
        PlatformSetting::set('payment_show_cash', $enabledMap['cash'] ? '1' : '0');
        PlatformSetting::set('payment_default_option', $defaultOption);
        PlatformSetting::set('payment_gateway', $gateway);

        if ($request->has('upi_id')) {
            $upiId = trim((string) ($validated['upi_id'] ?? ''));
            if ($enabledMap['full_upi'] && $upiId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Enter a valid UPI ID when Full UPI payment is enabled.',
                ], 422);
            }
            PlatformSetting::set('upi_id', $upiId ?: 'intercitywheels@upi');
        }
        if ($request->has('upi_payee_name')) {
            PlatformSetting::set('upi_payee_name', trim((string) ($validated['upi_payee_name'] ?? '')) ?: 'Intercity Wheels');
        }
        if ($request->has('upi_instructions')) {
            PlatformSetting::set('upi_instructions', trim((string) ($validated['upi_instructions'] ?? '')));
        }

        if ($request->has('razorpay_mode') && ! empty($validated['razorpay_mode'])) {
            PlatformSetting::set('razorpay_mode', $validated['razorpay_mode']);
        }
        if ($request->has('razorpay_key_id')) {
            PlatformSetting::set('razorpay_key_id', $keyIdInput);
        }
        if ($newSecretInput !== '') {
            PlatformSetting::set('razorpay_key_secret', $newSecretInput);
        }
        if ($request->has('razorpay_display_name')) {
            PlatformSetting::set('razorpay_display_name', trim((string) ($validated['razorpay_display_name'] ?? '')) ?: 'Intercity Wheels');
        }

        return response()->json([
            'success' => true,
            'message' => $gateway === 'razorpay'
                ? 'Checkout payment options & Razorpay gateway updated!'
                : 'Checkout payment options & advance percentage updated!',
            'settings' => PlatformSetting::allAdminSettings(),
        ]);
    }

    /**
     * Persist invoice template style, branding, GST rates, and footer notes.
     */
    public function updateInvoiceSettings(Request $request)
    {
        if (! auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin role required.',
            ], 403);
        }

        $validated = $request->validate([
            'invoice_template_style' => 'required|string|in:classic_gst,executive_dark,modern_minimal,compact_receipt',
            'invoice_accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'invoice_title' => 'required|string|max:60',
            'invoice_prefix' => 'required|string|max:20',
            'invoice_brand_name' => 'required|string|max:80',
            'invoice_company_legal' => 'required|string|max:160',
            'invoice_company_address' => 'required|string|max:300',
            'invoice_gstin' => 'nullable|string|max:40',
            'invoice_sac_code' => 'nullable|string|max:80',
            'invoice_place_of_supply' => 'nullable|string|max:80',
            'invoice_support_phone' => 'nullable|string|max:40',
            'invoice_support_email' => 'nullable|email|max:120',
            'invoice_cgst_pct' => 'required|numeric|min:0|max:25',
            'invoice_sgst_pct' => 'required|numeric|min:0|max:25',
            'invoice_show_stamp' => 'nullable|boolean',
            'invoice_show_otp' => 'nullable|boolean',
            'invoice_terms' => 'nullable|string|max:2000',
            'invoice_signatory_name' => 'nullable|string|max:120',
            'invoice_signatory_label' => 'nullable|string|max:80',
        ]);

        foreach (PlatformSetting::invoiceDefaults() as $key => $default) {
            if ($key === 'invoice_show_stamp' || $key === 'invoice_show_otp') {
                PlatformSetting::set($key, $request->boolean($key, true) ? '1' : '0');
                continue;
            }

            if (array_key_exists($key, $validated)) {
                $val = $validated[$key];
                PlatformSetting::set($key, $val === null ? '' : trim((string) $val));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Invoice template settings saved successfully!',
            'settings' => PlatformSetting::allAdminSettings(),
        ]);
    }

    /**
     * Persist editable Privacy Policy and Terms & Conditions page content.
     */
    public function updateLegalPages(Request $request)
    {
        if (! auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin role required.',
            ], 403);
        }

        $validated = $request->validate([
            'privacy_policy_title' => 'required|string|max:160',
            'privacy_policy_updated_at' => 'required|string|max:60',
            'privacy_policy_body' => 'required|string|max:20000',
            'terms_conditions_title' => 'required|string|max:160',
            'terms_conditions_updated_at' => 'required|string|max:60',
            'terms_conditions_body' => 'required|string|max:20000',
            'legal_contact_helpline' => 'nullable|string|max:60',
            'legal_contact_email' => 'nullable|email|max:120',
        ]);

        foreach (PlatformSetting::legalDefaults() as $key => $default) {
            if (array_key_exists($key, $validated)) {
                $val = $validated[$key];
                PlatformSetting::set($key, $val === null ? '' : trim((string) $val));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Privacy Policy & Terms & Conditions content updated!',
            'settings' => PlatformSetting::allAdminSettings(),
        ]);
    }
}
