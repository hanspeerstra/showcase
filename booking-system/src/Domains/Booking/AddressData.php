<?php

namespace Domains\Booking;

use Domains\Country\Country;

readonly class AddressData
{
    public function __construct(
        public ?string $street,
        public ?string $houseNumber,
        public ?string $postalCode,
        public ?string $city,
        public Country $country
    ) {}
}
