<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Fast2SmsService
{
    /**
     * Send OTP SMS via Fast2SMS API.
     */
    public function sendOtp(string $phone, string $otp): array
    {
        // Extract 10-digit Indian phone number
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleanPhone, '91') && strlen($cleanPhone) === 12) {
            $cleanPhone = substr($cleanPhone, 2);
        } elseif (strlen($cleanPhone) > 10) {
            $cleanPhone = substr($cleanPhone, -10);
        }

        $apiKey = config('services.fast2sms.api_key', env('FAST2SMS_API_KEY'));

        if (empty($apiKey)) {
            Log::info("Fast2SMS: No API key configured. Simulated OTP for {$cleanPhone}: {$otp}");

            return [
                'sent' => false,
                'provider' => 'fast2sms',
                'phone' => $cleanPhone,
                'otp' => $otp,
                'message' => 'Fast2SMS API key not set in .env. Showing simulated OTP for testing.',
                'is_demo' => true,
            ];
        }

        try {
            $route = config('services.fast2sms.route', env('FAST2SMS_ROUTE', 'otp'));

            $payload = [
                'variables_values' => $otp,
                'route' => $route,
                'numbers' => $cleanPhone,
            ];

            $response = Http::withHeaders([
                'authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://www.fast2sms.com/dev/bulkV2', $payload);

            $result = $response->json();

            if ($response->successful() && ! empty($result['return'])) {
                Log::info("Fast2SMS: OTP sent successfully to {$cleanPhone}");

                return [
                    'sent' => true,
                    'provider' => 'fast2sms',
                    'phone' => $cleanPhone,
                    'otp' => $otp,
                    'message' => 'OTP sent successfully to your mobile number via Fast2SMS.',
                    'is_demo' => false,
                ];
            }

            $errMsg = is_array($result['message'] ?? null)
                ? implode(', ', $result['message'])
                : ($result['message'] ?? 'Fast2SMS delivery error');

            Log::warning("Fast2SMS: API response note for {$cleanPhone}: {$errMsg}");

            return [
                'sent' => false,
                'provider' => 'fast2sms',
                'phone' => $cleanPhone,
                'otp' => $otp,
                'message' => "Fast2SMS gateway response: {$errMsg}. Displaying OTP for login.",
                'is_demo' => true,
            ];
        } catch (\Throwable $e) {
            Log::error("Fast2SMS: Exception while sending OTP to {$cleanPhone}: ".$e->getMessage());

            return [
                'sent' => false,
                'provider' => 'fast2sms',
                'phone' => $cleanPhone,
                'otp' => $otp,
                'message' => 'Fast2SMS connection error: '.$e->getMessage(),
                'is_demo' => true,
            ];
        }
    }
}
