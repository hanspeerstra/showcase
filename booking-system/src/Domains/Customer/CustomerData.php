<?php

namespace Domains\Customer;

readonly class CustomerData
{
    public function __construct(
        public ?string $name,
        public ?string $email,
        public ?string $phoneNumber
    ) {}
}
