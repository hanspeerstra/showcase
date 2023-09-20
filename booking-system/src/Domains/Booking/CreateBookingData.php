<?php

namespace Domains\Booking;

use DateTimeInterface;

readonly class CreateBookingData
{
    /**
     * @param GuestData[] $guests
     */
    public function __construct(
        public DateTimeInterface $arrivalDate,
        public DateTimeInterface $departureDate,
        public array $guests,
        int $extraGuests
    ) {}
}
