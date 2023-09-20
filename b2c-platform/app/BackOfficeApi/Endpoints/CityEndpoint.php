<?php

declare(strict_types=1);

namespace App\BackOfficeApi\Endpoints;

use App\Clients\Factories\CityEndpointRequestFactory;
use App\Clients\Factories\CityFactory;
use App\Clients\OfficeApiClient;
use App\Clients\Resources\City;

class CityEndpoint implements CityEndpointInterface
{
    /** @var OfficeApiClient */
    private $officeApiClient;
    /** @var CityEndpointRequestFactory */
    private $cityEndpointRequestFactory;
    /** @var CityFactory */
    private $cityFactory;

    public function __construct(
        OfficeApiClient $officeApiClient,
        CityEndpointRequestFactory $cityEndpointRequestFactory,
        CityFactory $cityFactory
    ) {
        $this->officeApiClient = $officeApiClient;
        $this->cityEndpointRequestFactory = $cityEndpointRequestFactory;
        $this->cityFactory = $cityFactory;
    }

    public function tryGetBySlug(string $slug): ?City
    {
        $response = $this->officeApiClient->sendHttpRequest(
            $this->cityEndpointRequestFactory->makeTryGetBySlugRequest($slug)
        );

        $contents = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);

        if ($contents->city === null) {
            return null;
        }

        return $this->cityFactory->makeFromApiResult($contents->city);
    }
}
