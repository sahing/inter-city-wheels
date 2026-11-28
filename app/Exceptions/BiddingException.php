<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A rule of the bidding marketplace was violated. Carries the HTTP status and a
 * stable machine code so the API and the driver page can branch on it without
 * string matching.
 */
class BiddingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly string $errorCode = 'bidding_error',
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function closed(): self
    {
        return new self(
            'Bidding for this booking has closed.',
            409,
            'bidding_closed'
        );
    }

    public static function expired(): self
    {
        return new self(
            'This bidding link has expired.',
            410,
            'bidding_expired'
        );
    }

    public static function notOpen(): self
    {
        return new self(
            'This bidding session is not open for bids.',
            409,
            'bidding_not_open'
        );
    }

    public static function alreadyAwarded(): self
    {
        return new self(
            'This booking has already been awarded to a driver.',
            409,
            'bidding_already_awarded'
        );
    }

    public static function alreadyOpen(int $sessionId): self
    {
        return new self(
            'Bidding is already open for this booking.',
            409,
            'bidding_already_open',
            ['session_id' => $sessionId]
        );
    }

    public static function bookingNotBiddable(string $status): self
    {
        return new self(
            "A booking in \"{$status}\" state cannot be sent to bidding.",
            422,
            'booking_not_biddable',
            ['status' => $status]
        );
    }

    public static function driverNotEligible(string $reason): self
    {
        return new self($reason, 403, 'driver_not_eligible');
    }

    public static function bidTooHigh(?float $currentLowest, float $startAmount, bool $allowEqualStart = false): self
    {
        $ceiling = $currentLowest ?? $startAmount;
        $message = ($currentLowest === null && $allowEqualStart)
            ? 'Your bid cannot exceed ₹'.number_format($ceiling, 0).'.'
            : 'Your bid must be lower than ₹'.number_format($ceiling, 0).'.';

        return new self(
            $message,
            422,
            'bid_not_low_enough',
            ['current_lowest' => $currentLowest, 'start_amount' => $startAmount]
        );
    }

    public static function bidTooLow(float $minDriverPrice): self
    {
        return new self(
            'Your bid cannot be lower than ₹'.number_format($minDriverPrice, 0).' (maximum service discount reached).',
            422,
            'bid_below_minimum',
            ['min_driver_price' => $minDriverPrice]
        );
    }

    public static function bidLimitReached(int $limit): self
    {
        return new self(
            "You have reached the {$limit}-bid limit for this auction.",
            429,
            'bid_limit_reached'
        );
    }

    public static function noBids(): self
    {
        return new self(
            'There are no bids to award yet.',
            422,
            'no_bids'
        );
    }

    public static function invalidStartAmount(float $max): self
    {
        return new self(
            'Starting price must be greater than zero and no more than ₹'
            .number_format($max, 0).' (the customer fare minus the admin margin).',
            422,
            'invalid_start_amount',
            ['max_driver_price' => $max]
        );
    }

    public static function invalidDeadline(): self
    {
        return new self(
            'The bidding deadline must be in the future.',
            422,
            'invalid_deadline'
        );
    }

    public function toArray(): array
    {
        return [
            'success' => false,
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
        ] + $this->context;
    }
}
