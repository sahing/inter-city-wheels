<?php

namespace App\Support;

class IndianPhone
{
    /**
     * Format a 10-digit Indian mobile number (with optional 0 or +91/91 prefix)
     * into the uniform "+91 XXXXX XXXXX" display and storage format.
     */
    public static function format(?string $raw): string
    {
        $trimmed = trim((string) $raw);
        if ($trimmed === '') {
            return '';
        }

        $digits = preg_replace('/[^0-9]/', '', $trimmed) ?? '';

        if (strlen($digits) === 10) {
            return '+91 '.substr($digits, 0, 5).' '.substr($digits, 5, 5);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $core = substr($digits, 1);

            return '+91 '.substr($core, 0, 5).' '.substr($core, 5, 5);
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $core = substr($digits, 2);

            return '+91 '.substr($core, 0, 5).' '.substr($core, 5, 5);
        }

        return $trimmed;
    }
}
