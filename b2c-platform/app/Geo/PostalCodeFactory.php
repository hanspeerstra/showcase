<?php

declare(strict_types=1);

namespace App\Geo;

class PostalCodeFactory
{
    private const POSTCODE_REGEX = '/^(?<postcode>[1-9][0-9]{3})\s*(?<letters>[a-zA-Z]{2})?$/';

    public function make(string $postalCode): ?PostalCode
    {
        if (preg_match(self::POSTCODE_REGEX, $postalCode, $matches)) {
            return new PostalCode(
                (int) $matches['postcode'],
                $matches['letters'] ?? null
            );
        }

        return null;
    }
}
