<?php

namespace Domains\Booking;

use Domains\Product\Models\Product;

class BookingOption
{
    public function __construct(
        public Product $product,
        public bool $available
    ) {}
}
