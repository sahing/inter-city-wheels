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
     * Default checkout payment options and advance percentage.
     *
     * @return array<string, string>
     */
    public static function paymentDefaults(): array
    {
        return [
            'payment_advance_pct' => '20',
            'payment_show_full_online' => '1',
            'payment_show_part_pay' => '1',
            'payment_show_full_upi' => '1',
            'payment_show_cash' => '1',
            'payment_default_option' => 'full_online',
            'payment_gateway' => 'manual',
            'razorpay_mode' => 'test',
            'razorpay_key_id' => '',
            'razorpay_display_name' => 'Intercity Wheels',
        ];
    }

    /**
     * Load all configurable checkout payment settings with defaults.
     * Never exposes the Razorpay Key Secret to public view payloads.
     *
     * @return array<string, string>
     */
    public static function paymentSettings(): array
    {
        $defaults = static::paymentDefaults();
        $stored = static::whereIn('key', array_keys($defaults))->pluck('value', 'key')->all();

        $resolved = [];
        foreach ($defaults as $key => $default) {
            $val = $stored[$key] ?? null;
            $resolved[$key] = ($val !== null && $val !== '') ? (string) $val : $default;
        }

        $secret = static::get('razorpay_key_secret', '');
        $resolved['razorpay_secret_configured'] = ($secret !== null && trim($secret) !== '') ? '1' : '0';

        return $resolved;
    }

    /**
     * Server-only Razorpay credentials (Key ID + Secret). Never serialize to views.
     *
     * @return array{enabled: bool, mode: string, key_id: string, key_secret: string, display_name: string}
     */
    public static function razorpayCredentials(): array
    {
        $gateway = static::get('payment_gateway', 'manual') ?? 'manual';
        $keyId = trim((string) (static::get('razorpay_key_id', '') ?? ''));
        $keySecret = trim((string) (static::get('razorpay_key_secret', '') ?? ''));

        if ($keyId === '' && config('services.razorpay.key_id')) {
            $keyId = trim((string) config('services.razorpay.key_id'));
        }
        if ($keySecret === '' && config('services.razorpay.key_secret')) {
            $keySecret = trim((string) config('services.razorpay.key_secret'));
        }

        return [
            'enabled' => $gateway === 'razorpay',
            'mode' => static::get('razorpay_mode', 'test') ?? 'test',
            'key_id' => $keyId,
            'key_secret' => $keySecret,
            'display_name' => trim((string) (static::get('razorpay_display_name', 'Intercity Wheels') ?? 'Intercity Wheels')) ?: 'Intercity Wheels',
        ];
    }

    /**
     * Default invoice template style, company branding, GST rates, and terms.
     *
     * @return array<string, string>
     */
    public static function invoiceDefaults(): array
    {
        return [
            'invoice_template_style' => 'classic_gst',
            'invoice_accent_color' => '#d49b4c',
            'invoice_title' => 'TAX INVOICE',
            'invoice_prefix' => 'INV',
            'invoice_brand_name' => 'INTERCITY WHEELS',
            'invoice_company_legal' => 'IntercityWheels Mobility Services Pvt. Ltd.',
            'invoice_company_address' => 'Floor 4, Tech Vista Towers, Senapati Bapat Road, Pune - 411016, Maharashtra, India',
            'invoice_gstin' => '27AAACI9912K1Z8',
            'invoice_sac_code' => '996412 (Passenger Transport Service)',
            'invoice_place_of_supply' => 'Maharashtra (27)',
            'invoice_support_phone' => '700 3434 088',
            'invoice_support_email' => 'billing@intercitywheels.com',
            'invoice_cgst_pct' => '2.5',
            'invoice_sgst_pct' => '2.5',
            'invoice_show_stamp' => '1',
            'invoice_show_otp' => '1',
            'invoice_terms' => "1. This is a computer-generated tax invoice and complies with Section 31 of CGST Act 2017.\n2. All tolls and state border permits are included per the scheduled booking agreement.\n3. Disputes are subject to Pune, Maharashtra jurisdiction.",
            'invoice_signatory_name' => 'IntercityWheels Mobility Services',
            'invoice_signatory_label' => 'Authorized Signatory',
        ];
    }

    /**
     * Load all configurable invoice template settings with defaults.
     *
     * @return array<string, string>
     */
    public static function invoiceSettings(): array
    {
        $defaults = static::invoiceDefaults();
        $stored = static::whereIn('key', array_keys($defaults))->pluck('value', 'key')->all();

        $resolved = [];
        foreach ($defaults as $key => $default) {
            $val = $stored[$key] ?? null;
            $resolved[$key] = ($val !== null && $val !== '') ? (string) $val : $default;
        }

        return $resolved;
    }

    /**
     * Default editable content for public Privacy Policy and Terms & Conditions pages.
     *
     * @return array<string, string>
     */
    public static function legalDefaults(): array
    {
        return [
            'privacy_policy_title' => 'IntercityWheels Privacy Policy',
            'privacy_policy_updated_at' => 'November 2026',
            'privacy_policy_body' => "IntercityWheels Mobility Services Pvt. Ltd. is committed to protecting your personal information. When you book outstation cabs or sleeper berths through our platform, we collect minimal operational data including name, contact phone, pickup/drop GPS coordinates, and payment receipts solely to deliver and coordinate transport services.\n\n1. Operational Scope & Data Protection\nWe use your contact details, pickup/drop locations, and trip manifests strictly to dispatch verified chauffeurs, calculate transparent toll and GST billing, and send booking updates.\n\n2. Data Retention & Security\nRide records and OTP verifications are stored securely and are never sold or shared with unauthorized third parties.",
            'terms_conditions_title' => 'IntercityWheels Rider & Chauffeur Terms & Conditions',
            'terms_conditions_updated_at' => 'November 2026',
            'terms_conditions_body' => "By accessing or using IntercityWheels.com, customers and driver partners agree to be bound by these Terms of Service. All outstation trips include transparent per-km billing, toll charges, night allowance rules, and real-time OTP validation for verified chauffeur departure.\n\n1. Operational Scope & Service Fulfillment\nIntercityWheels provides verified chauffeur-driven outstation cabs and scheduled sleeper berths connecting major intercity routes and regional transit hubs. Users agree to share accurate pickup locations and verify the 4-digit ride OTP before boarding.\n\n2. Transparent Pricing & Toll Escrow\nAll displayed fares include base km distance, state highway tolls, and applicable GST. Driver bids are binding upon confirmation.",
            'legal_contact_helpline' => '700 3434 088',
            'legal_contact_email' => 'support@intercitywheels.com',
        ];
    }

    /**
     * Load editable Privacy Policy & Terms page content with defaults.
     *
     * @return array<string, string>
     */
    public static function legalSettings(): array
    {
        $defaults = static::legalDefaults();
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
        ], static::pricingSettings(), static::paymentSettings(), static::invoiceSettings(), static::legalSettings());
    }
}
