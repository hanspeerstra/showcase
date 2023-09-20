<?php

declare(strict_types=1);

namespace App\Geo\PDOK;

use GuzzleHttp\Psr7\Request;

class PDOKRequestFactory
{
    public function makeFindAddressesByPostcodeAndHouseNumberRequest(string $postcode, string $houseNumberFull): Request
    {
        $houseNumber = $this->extractHouseNumber($houseNumberFull);

        $filterQuery = sprintf(
            'type:adres AND postcode:%s AND huisnummer:%s',
            $postcode,
            $houseNumber
        );

        $searchQuery = $houseNumber === $houseNumberFull
            ? null
            : sprintf('"%s"', $houseNumberFull);

        $params = [
            'fq' => $filterQuery,
            'q' => $searchQuery,
        ];

        $uri = http_build_query($params);

        return new Request('get', '?' . $uri);
    }

    public function makeFindPostcodesRequest(string $postcode): Request
    {
        $filterQuery = 'type:postcode';

        $searchQuery = $postcode;

        $params = [
            'fq' => $filterQuery,
            'q' => $searchQuery,
        ];

        $uri = http_build_query($params);

        return new Request('get', '?' . $uri);
    }

    private function extractHouseNumber(string $houseNumberWithExtension): string
    {
        return substr(
            $houseNumberWithExtension,
            0,
            strspn($houseNumberWithExtension, '0123456789')
        );
    }
}
