<?php

declare(strict_types=1);

namespace App\Clients\Factories;

use App\Clients\OfficeApiClient;
use GuzzleHttp\Psr7\Request;

class CityEndpointRequestFactory
{
    public function makeFindByPostcodeRequest(int $postcode): Request
    {
        return new Request(
            'get',
            '/api/cities/show-by-postcode/' . $postcode,
            OfficeApiClient::JSON_HEADERS
        );
    }

    public function makeGetByNameRequest(string $name): Request
    {
        return new Request(
            'get',
            '/api/cities/show-by-name/' . $name,
            OfficeApiClient::JSON_HEADERS
        );
    }

    public function makeTryGetBySlugRequest(string $slug): Request
    {
        return new Request(
            'get',
            '/api/cities/show-by-slug/' . $slug,
            OfficeApiClient::JSON_HEADERS
        );
    }
}
