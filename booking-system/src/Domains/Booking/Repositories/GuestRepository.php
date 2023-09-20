<?php

namespace Domains\Booking\Repositories;

use Domains\Booking\Models\Guest;

class GuestRepository
{
    public function getById(int $id): Guest
    {
        return Guest::query()
            ->findOrFail($id);
    }

    public function update(Guest $guest): void
    {
        $guest->save();
    }
}
