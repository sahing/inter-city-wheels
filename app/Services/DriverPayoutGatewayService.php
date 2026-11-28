<?php

namespace App\Services;

use App\Models\DriverPayoutRequest;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DriverPayoutGatewayService
{
    public function isReady(): bool
    {
        $creds = PlatformSetting::razorpayCredentials();
        $accountNumber = $this->accountNumber();

        return $creds['enabled']
            && $creds['key_id'] !== ''
            && $creds['key_secret'] !== ''
            && $accountNumber !== '';
    }

    public function accountNumber(): string
    {
        $configured = trim((string) (PlatformSetting::get('razorpayx_account_number', '') ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        return trim((string) (config('services.razorpay.account_number') ?? ''));
    }

    /**
     * Dispatch a live payout via RazorpayX Payouts API using the driver's
     * saved UPI VPA or Bank Account details.
     *
     * @return array{provider: string, payout_id: string, transaction_reference: string, response: array<string, mixed>}
     */
    public function sendPayout(DriverPayoutRequest $payoutRequest): array
    {
        $creds = PlatformSetting::razorpayCredentials();
        $accountNumber = $this->accountNumber();

        if (! $creds['enabled'] || $creds['key_id'] === '' || $creds['key_secret'] === '' || $accountNumber === '') {
            throw new RuntimeException('Payment Gateway Payout API requires Razorpay Key ID, Key Secret, and RazorpayX Account Number configured in System Settings.');
        }

        $dest = $payoutRequest->payout_destination ?? [];
        $driver = $payoutRequest->driver;
        $amountPaise = max(100, (int) round(((float) $payoutRequest->amount) * 100));

        $fundAccount = $payoutRequest->payout_method === 'upi'
            ? [
                'account_type' => 'vpa',
                'vpa' => [
                    'address' => (string) ($dest['upi_id'] ?? $driver?->payout_upi_id ?? ''),
                ],
                'contact' => [
                    'name' => (string) ($dest['upi_name'] ?? $driver?->name ?? 'Driver Partner'),
                    'contact' => (string) ($driver?->phone ?? ''),
                    'type' => 'vendor',
                    'reference_id' => 'DRV-'.$payoutRequest->driver_id,
                ],
            ]
            : [
                'account_type' => 'bank_account',
                'bank_account' => [
                    'name' => (string) ($dest['bank_account_holder'] ?? $driver?->bank_account_holder ?? $driver?->name ?? 'Driver Partner'),
                    'ifsc' => strtoupper((string) ($dest['bank_ifsc'] ?? $driver?->bank_ifsc ?? '')),
                    'account_number' => (string) ($dest['bank_account_number'] ?? $driver?->bank_account_number ?? ''),
                ],
                'contact' => [
                    'name' => (string) ($dest['bank_account_holder'] ?? $driver?->name ?? 'Driver Partner'),
                    'contact' => (string) ($driver?->phone ?? ''),
                    'type' => 'vendor',
                    'reference_id' => 'DRV-'.$payoutRequest->driver_id,
                ],
            ];

        $response = Http::withBasicAuth($creds['key_id'], $creds['key_secret'])
            ->acceptJson()
            ->timeout(15)
            ->post('https://api.razorpay.com/v1/payouts', [
                'account_number' => $accountNumber,
                'amount' => $amountPaise,
                'currency' => 'INR',
                'mode' => $payoutRequest->payout_method === 'upi' ? 'UPI' : 'IMPS',
                'purpose' => 'payout',
                'queue_if_low_balance' => true,
                'reference_id' => $payoutRequest->payout_reference,
                'narration' => 'Intercity Wheels Driver Payout',
                'fund_account' => $fundAccount,
            ]);

        if (! $response->successful()) {
            $errMsg = $response->json('error.description')
                ?? $response->json('message')
                ?? 'Payment Gateway payout API rejected the transfer.';

            throw new RuntimeException((string) $errMsg);
        }

        $json = $response->json() ?? [];
        $payoutId = (string) ($json['id'] ?? '');
        if ($payoutId === '') {
            throw new RuntimeException('Payment Gateway did not return a valid payout ID.');
        }

        $utr = trim((string) ($json['utr'] ?? ''));

        return [
            'provider' => 'razorpayx',
            'payout_id' => $payoutId,
            'transaction_reference' => $utr !== '' ? $utr : $payoutId,
            'response' => is_array($json) ? $json : [],
        ];
    }
}
