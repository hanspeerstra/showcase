<?php

namespace Domains\Booking\Services;

use DateTimeInterface;
use Domains\Booking\Availability;
use Domains\Booking\Models\Booking;
use Domains\Booking\BookingOption;
use Domains\Booking\Services\ChangeoverDayService;
use Domains\Product\Models\Maintenance;
use Domains\Product\Models\Product;
use Domains\Product\Repositories\ProductRepository;
use InvalidArgumentException;

readonly class AvailabilityService
{
    public function __construct(
        private ProductRepository $productRepository,
        private ChangeoverDayService $changeoverDayService
    ) {}

    public function getAvailability(DateTimeInterface $arrivalDate, DateTimeInterface $departureDate): Availability
    {
        $this->assertTravelPeriodAllowed($arrivalDate, $departureDate);

        $products = $this->productRepository->getAll();

        $unavailableProducts = $this->getUnavailableProducts($arrivalDate, $departureDate);

        $maxPersons = 0;
        $bookingOptions = [];
        foreach ($products as $product) {
            foreach ($unavailableProducts as $unavailableProduct) {
                if ($product->getId() === $unavailableProduct->getId()) {
                    $bookingOptions[] = new BookingOption(
                        $product,
                        false
                    );

                    continue 2;
                }
            }

            $maxPersons += 2;
            $bookingOptions[] = new BookingOption(
                $product,
                true
            );
        }

        return new Availability(
            $maxPersons,
            $bookingOptions
        );
    }

    /**
     * @return Product[]
     */
    private function getUnavailableProducts(DateTimeInterface $arrivalDate, DateTimeInterface $departureDate): array
    {
        return [];
    }

    private function assertTravelPeriodAllowed(DateTimeInterface $arrivalDate, DateTimeInterface $departureDate): void
    {
        $checkInDays = $this->changeoverDayService->getCheckInDaysWithinInterval($arrivalDate, $departureDate);
        $checkOutDays = $this->changeoverDayService->getCheckOutDaysWithinInterval($arrivalDate, $departureDate);

        if (reset($checkInDays)->date <> $arrivalDate || end($checkOutDays)->date <> $departureDate) {
            throw new InvalidArgumentException('Arrival date and/or departure date is not a changeover day.');
        }
    }
}
