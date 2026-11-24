<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a booking is asked to move to a status the workflow forbids
 * (for example Confirmed -> Completed, skipping the trip itself).
 */
class InvalidBookingTransition extends RuntimeException
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly array $allowed = [],
    ) {
        $allowedText = $allowed === [] ? 'none (terminal state)' : implode(', ', $allowed);

        parent::__construct(
            "Illegal booking transition: {$from} -> {$to}. Allowed from {$from}: {$allowedText}."
        );
    }

    /**
     * Structured payload for JSON error responses.
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'allowed' => $this->allowed,
        ];
    }
}
