<?php

namespace Domains\Setting;

use DateTimeInterface;
use InvalidArgumentException;

readonly class Season
{
    public function __construct(
        public int $year,
        public ?DateTimeInterface $startDate,
        public ?DateTimeInterface $endDate
    ) {
        if ($this->startDate === null && $this->endDate === null) {
            // Season is not defined yet.
        } elseif ($this->startDate->format('Y') <> $this->endDate->format('Y')) {
            throw new InvalidArgumentException('Start and end date of season must lie in the same year.');
        }
    }
}
