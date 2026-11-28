<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One-time-password issuing and verification for mobile login.
 *
 * P1-5: the previous implementation compared against a session value that
 * never expired, accepted the hardcoded code "1234" for every phone number,
 * and allowed unlimited guesses. That is an account-takeover path: an attacker
 * could request a code for any number, read it straight out of the JSON
 * response, or simply send "1234".
 *
 * Now: codes are generated with random_int, stored hashed, expire after a
 * fixed TTL, allow a bounded number of attempts, and issuing is rate limited
 * per phone.
 */
class OtpService
{
    /** How long a code stays valid, in seconds. */
    public const TTL_SECONDS = 600;

    /** Wrong guesses allowed before the code is burned. */
    public const MAX_ATTEMPTS = 5;

    /**
     * Extra storage lifetime beyond TTL_SECONDS, so an expired code can be
     * reported as "expired" rather than "no such code".
     */
    public const GRACE_SECONDS = 900;

    /** Issuing more than this per phone inside the window is throttled. */
    public const MAX_ISSUES = 3;

    /** Throttle window, in seconds. */
    public const ISSUE_WINDOW_SECONDS = 600;

    /**
     * Issue (or re-issue) a code.
     *
     * @return array{otp: string, expires_in: int, throttled: bool, retry_after: int}
     */
    public function issue(string $phone, string $purpose = 'login'): array
    {
        $throttleKey = $this->throttleKey($phone, $purpose);

        /** @var array<int, int> $issued */
        $issued = Cache::get($throttleKey, []);

        // 0 = no expiry; the timestamps themselves are filtered below.
        $now = now()->timestamp;
        $issued = array_values(array_filter(
            $issued,
            static fn (int $ts): bool => $ts > $now - self::ISSUE_WINDOW_SECONDS
        ));

        if (count($issued) >= self::MAX_ISSUES) {
            $retryAfter = max(1, ($issued[0] + self::ISSUE_WINDOW_SECONDS) - $now);

            return [
                'otp' => '',
                'expires_in' => 0,
                'throttled' => true,
                'retry_after' => $retryAfter,
            ];
        }

        $issued[] = $now;
        Cache::put($throttleKey, $issued, self::ISSUE_WINDOW_SECONDS);

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        Cache::put($this->otpKey($phone, $purpose), [
            'hash' => $this->hash($phone, $purpose, $otp),
            'expires_at' => $now + self::TTL_SECONDS,
            'attempts' => 0,
        ], self::TTL_SECONDS + self::GRACE_SECONDS);

        return [
            'otp' => $otp,
            'expires_in' => self::TTL_SECONDS,
            'throttled' => false,
            'retry_after' => 0,
        ];
    }

    /**
     * Verify a code. A correct code is consumed; a wrong code counts against
     * the attempt budget and the code is destroyed once that budget is spent.
     *
     * @return array{ok: bool, reason: string, attempts_left: int}
     */
    public function verify(string $phone, string $otp, string $purpose = 'login'): array
    {
        $key = $this->otpKey($phone, $purpose);
        $payload = Cache::get($key);

        if (! is_array($payload) || ! isset($payload['hash'])) {
            return ['ok' => false, 'reason' => 'missing', 'attempts_left' => 0];
        }

        if (($payload['expires_at'] ?? 0) < now()->timestamp) {
            Cache::forget($key);

            return ['ok' => false, 'reason' => 'expired', 'attempts_left' => 0];
        }

        $attempts = (int) ($payload['attempts'] ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            return ['ok' => false, 'reason' => 'locked', 'attempts_left' => 0];
        }

        $candidate = $this->hash($phone, $purpose, trim($otp));

        if (! hash_equals((string) $payload['hash'], $candidate)) {
            $payload['attempts'] = $attempts + 1;
            $remaining = max(0, self::MAX_ATTEMPTS - $payload['attempts']);

            if ($remaining === 0) {
                Cache::forget($key);

                return ['ok' => false, 'reason' => 'locked', 'attempts_left' => 0];
            }

            Cache::put($key, $payload, max(1, ($payload['expires_at'] ?? 0) - now()->timestamp + self::GRACE_SECONDS));

            return ['ok' => false, 'reason' => 'invalid', 'attempts_left' => $remaining];
        }

        Cache::forget($key);

        return ['ok' => true, 'reason' => 'valid', 'attempts_left' => 0];
    }

    /**
     * Normalise an Indian mobile number to its 10-digit core.
     */
    public function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/[^0-9]/', '', $raw) ?? '';

        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            return substr($digits, 2);
        }

        if (strlen($digits) > 10) {
            return substr($digits, -10);
        }

        return $digits;
    }

    private function hash(string $phone, string $purpose, string $otp): string
    {
        return hash_hmac('sha256', $phone.'|'.$purpose.'|'.$otp, (string) config('app.key'));
    }

    private function otpKey(string $phone, string $purpose): string
    {
        return 'otp:'.Str::slug($purpose).':'.$phone;
    }

    private function throttleKey(string $phone, string $purpose): string
    {
        return 'otp-issue:'.Str::slug($purpose).':'.$phone;
    }
}
