<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RazorpayService
{
    public function isReady(): bool
    {
        $creds = PlatformSetting::razorpayCredentials();

        return $creds['enabled']
            && $creds['key_id'] !== ''
            && $creds['key_secret'] !== '';
    }

    /**
     * Create a Razorpay Order via Orders API for the booking's payable-now amount.
     *
     * @return array{order_id: string, amount_paise: int, currency: string, key_id: string, display_name: string, mode: string}
     */
    public function createOrder(Booking $booking, float $amountInr): array
    {
        return $this->createCustomOrder(
            receipt: substr((string) $booking->booking_reference, 0, 40),
            amountInr: $amountInr,
            notes: [
                'booking_reference' => (string) $booking->booking_reference,
                'payment_mode' => (string) ($booking->payment_mode ?? 'full_online'),
                'route_summary' => (string) $booking->route_summary,
            ],
        );
    }

    /**
     * Create a Razorpay Order for a generic platform receipt (e.g. driver company-due settlement).
     *
     * @param  array<string, string>  $notes
     * @return array{order_id: string, amount_paise: int, currency: string, key_id: string, display_name: string, mode: string}
     */
    public function createCustomOrder(string $receipt, float $amountInr, array $notes = []): array
    {
        $creds = PlatformSetting::razorpayCredentials();

        if (! $creds['enabled'] || $creds['key_id'] === '' || $creds['key_secret'] === '') {
            throw new RuntimeException('Razorpay is not configured by the platform administrator.');
        }

        // Razorpay requires minimum 100 paise (₹1.00).
        $amountPaise = max(100, (int) round($amountInr * 100));

        $response = Http::withBasicAuth($creds['key_id'], $creds['key_secret'])
            ->acceptJson()
            ->timeout(12)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => substr($receipt, 0, 40),
                'notes' => $notes,
            ]);

        if (! $response->successful()) {
            $errMsg = $response->json('error.description')
                ?? $response->json('message')
                ?? 'Unable to initiate Razorpay checkout order.';

            throw new RuntimeException((string) $errMsg);
        }

        $orderId = (string) ($response->json('id') ?? '');
        if ($orderId === '') {
            throw new RuntimeException('Razorpay did not return a valid order id.');
        }

        return [
            'order_id' => $orderId,
            'amount_paise' => $amountPaise,
            'currency' => (string) ($response->json('currency') ?? 'INR'),
            'key_id' => $creds['key_id'],
            'display_name' => $creds['display_name'],
            'mode' => $creds['mode'],
        ];
    }

    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $creds = PlatformSetting::razorpayCredentials();
        if ($creds['key_secret'] === '' || $orderId === '' || $paymentId === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $creds['key_secret']);

        return hash_equals($expected, $signature);
    }
}
