<?php

namespace Domains\Booking\Repositories;

use Domains\Booking\Models\Booking;

class BookingRepository
{
    /**
     * @return Booking[]
     */
    public function getByIdList(int ...$idList): iterable
    {
        return Booking::query()->findMany($idList);
    }

    public function insert(Booking $booking): Booking
    {
        $booking->save();

        return $booking;
    }

    public function update(Booking $booking): Booking
    {
        $booking->save();

        return $booking;
    }
}
