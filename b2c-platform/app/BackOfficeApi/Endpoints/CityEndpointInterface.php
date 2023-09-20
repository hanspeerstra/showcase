<?php

declare(strict_types=1);

namespace App\BackOfficeApi\Endpoints;

use App\Clients\Resources\City;

interface CityEndpointInterface
{
    public function tryGetBySlug(string $slug): ?City;
}
