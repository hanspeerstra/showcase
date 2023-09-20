<?php

declare(strict_types=1);

namespace App\Clients\Factories;

use App\Clients\OfficeApiClient;
use GuzzleHttp\Psr7\Request;

class RegionEndpointRequestFactory
{
    public function makeTryGetByNameRequest(string $name): Request
    {
        return new Request(
            'get',
            '/api/regions/show-by-name/' . $name,
            OfficeApiClient::JSON_HEADERS
        );
    }
}
