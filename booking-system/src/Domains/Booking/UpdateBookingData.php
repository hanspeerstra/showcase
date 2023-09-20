<?php

namespace Domains\Booking;

use Domains\Customer\CustomerData;

readonly class UpdateBookingData
{
    /**
     * @param GuestData[] $guests
     */
    public function __construct(
        public CustomerData $customerData,
        public AddressData $addressData,
        public array $guests
    ) {}
}
