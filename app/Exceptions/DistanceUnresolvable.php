<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when no trustworthy distance can be established for a trip.
 *
 * Before this existed the platform silently invented a distance (a 345 km
 * constant, or a "central West Bengal" coordinate guess) for any city it did
 * not recognise, so a customer could be quoted and charged for a journey
 * nobody had measured. Failing loudly is the only honest option.
 */
class DistanceUnresolvable extends RuntimeException
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct(
            "We could not measure a distance between \"{$from}\" and \"{$to}\". "
            .'Pick a listed city or supply pickup/drop coordinates.'
        );
    }

    public function toArray(): array
    {
        return ['from' => $this->from, 'to' => $this->to];
    }
}
