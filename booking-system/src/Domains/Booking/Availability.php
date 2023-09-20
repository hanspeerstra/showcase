<?php

namespace Domains\Booking;

class Availability
{
    /**
     * @param BookingOption[] $bookingOptions
     */
    public function __construct(
        public int $maxPersons,
        public array $bookingOptions
    ) {}
}
