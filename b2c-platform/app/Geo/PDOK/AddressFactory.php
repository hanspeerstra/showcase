<?php

declare(strict_types=1);

namespace App\Geo\PDOK;

class AddressFactory
{
    private const POINT_REGEX = '/POINT\\((?<longitude>\\d+\\.\\d+) (?<latitude>\\d+\\.\\d+\\))/';

    public function makeFromApiResult(array $data): Address
    {
        $latitude = null;
        $longitude = null;

        if (preg_match(self::POINT_REGEX, $data['centroide_ll'], $matches)) {
            $latitude = (float) $matches['latitude'];
            $longitude = (float) $matches['longitude'];
        }

        return new Address(
            $data['postcode'],
            $data['huisnummer'],
            $data['huis_nlt'],
            $data['straatnaam'],
            $data['woonplaatsnaam'],
            $data['weergavenaam'],
            $latitude,
            $longitude
        );
    }
}
