<?php

declare(strict_types=1);

namespace App\Geo\PDOK;

class Postcode
{
    /** @var string */
    private $postcode;

    public function __construct(string $postcode)
    {
        $this->postcode = $postcode;
    }

    public function getPostcode(): string
    {
        return $this->postcode;
    }
}
