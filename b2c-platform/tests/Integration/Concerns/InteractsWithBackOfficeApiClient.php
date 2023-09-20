<?php

declare(strict_types=1);

namespace Tests\Integration\Concerns;

use App\Clients\Resources\City;
use App\Clients\Resources\Company;
use App\Clients\Resources\Profession;
use stdClass;

trait InteractsWithBackOfficeApiClient
{
    private function givenCity(): City
    {
        return new City(
            1,
            'groningen',
            'Groningen',
            null,
            null
        );
    }
}
