<?php

declare(strict_types=1);

namespace App\Clients\Factories;

use App\Clients\Resources\QualityMark;

class QualityMarkFactory
{
    public function makeFromApiResult(object $data): QualityMark
    {
        return new QualityMark(
            $data->id,
            $data->name,
            $data->slug,
            $data->logo,
            $data->type
        );
    }
}
