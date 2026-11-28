<?php

namespace App\Exceptions;

use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Raised when one or more requested berths were taken between selection and
 * confirmation. Maps to HTTP 409 so the client can tell a race apart from a
 * malformed request.
 */
class BerthUnavailable extends RuntimeException
{
    /**
     * @param  list<string>  $berths
     */
    public function __construct(public readonly array $berths, public readonly ?string $coachCode = null)
    {
        $list = implode(', ', $berths);

        parent::__construct("These berths are no longer available: {$list}. Please choose another berth.");
    }

    public static function fromRows(Collection $rows): self
    {
        return new self(
            $rows->pluck('berth_number')->values()->all(),
            $rows->first()->coach_code ?? null
        );
    }

    public function toArray(): array
    {
        return ['berths' => $this->berths, 'coach_code' => $this->coachCode];
    }
}
