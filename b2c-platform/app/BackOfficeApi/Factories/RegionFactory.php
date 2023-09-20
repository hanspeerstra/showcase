<?php

declare(strict_types=1);

namespace App\Clients\Factories;

use App\Clients\Resources\Region;

class RegionFactory
{
    public function makeFromApiResult(object $data): Region
    {
        return new Region(
            $data->id,
            $data->name
        );
    }
}
