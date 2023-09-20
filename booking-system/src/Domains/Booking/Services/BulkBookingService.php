<?php

namespace Domains\Booking\Services;

use Domains\Booking\Models\Booking;

readonly class BulkBookingService
{
    public function __construct(
        private BookingService $bookingService
    ) {}

    public function delete(Booking ...$bookings): void
    {
        foreach ($bookings as $booking) {
            $this->bookingService->delete($booking);
        }
    }

    public function erasePersonalData(Booking ...$bookings): void
    {
        foreach ($bookings as $booking) {
            $this->bookingService->erasePersonalData($booking);
        }
    }
}
