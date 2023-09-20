<?php

namespace Domains\Booking;

use DateTimeInterface;

readonly class ChangeoverDay
{
    public function __construct(public DateTimeInterface $date)
    {}
}
