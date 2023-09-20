<?php

declare(strict_types=1);

namespace App\Clients\Factories;

use App\Clients\Resources\City;

class CityFactory
{
    public function makeFromApiResult(object $data): City
    {
        return new City(
            $data->id,
            $data->slug,
            $data->name,
            $data->latitude,
            $data->longitude
        );
    }
}
