<?php

namespace App\Services;

use App\Models\Booking;
use RuntimeException;

/**
 * Generates booking references and ride-start OTPs.
 *
 * P0-7: both used mt_rand(). References collided (only 90,000 possible values
 * for a growing platform) and the OTP was predictable. References now come
 * from a CSPRNG and are checked against the unique index, the OTP from
 * random_int.
 */
class BookingReferenceGenerator
{
    private const REFERENCE_PREFIX = 'ICW-';

    /** Six digits keeps the human-readable format with 900k values. */
    private const REFERENCE_MIN = 100000;

    private const REFERENCE_MAX = 999999;

    private const MAX_ATTEMPTS = 10;

    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $reference = self::REFERENCE_PREFIX.random_int(self::REFERENCE_MIN, self::REFERENCE_MAX);

            if (! Booking::where('booking_reference', $reference)->exists()) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not allocate a unique booking reference.');
    }

    /**
     * Four-digit ride-start OTP handed to the passenger and typed by the driver.
     */
    public function startOtp(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
