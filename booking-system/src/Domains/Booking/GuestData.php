<?php

namespace Domains\Booking;

use DateTimeInterface;

readonly class GuestData
{
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?DateTimeInterface $dateOfBirth
    ) {}
}
