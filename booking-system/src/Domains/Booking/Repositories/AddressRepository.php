<?php

namespace Domains\Booking\Repositories;

use Domains\Booking\Models\Address;

class AddressRepository
{
    public function update(Address $address): void
    {
        $address->save();
    }
}
