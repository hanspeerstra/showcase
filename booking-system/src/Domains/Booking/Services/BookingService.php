<?php

namespace Domains\Booking\Services;

use Domains\Booking\CreateBookingData;
use Domains\Booking\Models\Address;
use Domains\Booking\Models\Booking;
use Domains\Booking\UpdateBookingData;
use Domains\Booking\BookingFinderFilters;
use Domains\Booking\Repositories\BookingRepository;
use Domains\Booking\Repositories\GuestRepository;
use Domains\Booking\PaymentType;
use Domains\Booking\Repositories\AddressRepository;
use Domains\Customer\Repositories\CustomerRepository;
use Domains\Shared\PaginatedResult;
use Domains\Shared\PaginatedResultFactory;
use Domains\Shared\Sort;
use Illuminate\Database\ConnectionInterface;

class BookingService
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly PaginatedResultFactory $paginatedResultFactory,
        private readonly ConnectionInterface $db
    ) {}

    /**
     * @return PaginatedResult<Booking>
     */
    public function find(
        int $page,
        int $perPage,
        ?string $search,
        BookingFinderFilters $filters,
        ?Sort $sort
    ): PaginatedResult {
        $query = Booking::query();

        return $this->paginatedResultFactory->make($query, $page, $perPage);
    }

    public function create(CreateBookingData $bookingData): Booking
    {
        $booking = Booking::makeInstance();

        return $this->bookingRepository->insert($booking);
    }

    public function update(Booking $booking, UpdateBookingData $bookingData): Booking
    {
        return $booking;
    }

    public function delete(Booking $booking): void
    {
        $booking->markAsTrash();

        $this->bookingRepository->update($booking);
    }

    public function erasePersonalData(Booking $booking): void
    {
        $this->db->transaction(function () use ($booking) {
            $customer = $booking->getCustomer();

            $customer->setEmailAddress(null);

            $this->customerRepository->update($customer);

            $booking->markPersonalDataAsErased();

            $this->bookingRepository->update($booking);
        });
    }
}
