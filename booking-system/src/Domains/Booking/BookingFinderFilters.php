<?php

namespace Domains\Booking;

use Domains\Product\Models\Product;

readonly class BookingFinderFilters
{
    public function __construct(
        public ?Product $product,
        public ?int $fiscalYear,
        public bool $notInTrash
    ) {}

    public function isProductFilterEnabled(): bool
    {
        return $this->product instanceof Product;
    }

    public function isFiscalYearFilterEnabled(): bool
    {
        return $this->fiscalYear > 0;
    }
}
