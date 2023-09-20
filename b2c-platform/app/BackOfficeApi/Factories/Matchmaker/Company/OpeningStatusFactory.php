<?php

declare(strict_types=1);

namespace App\Clients\Factories\Matchmaker\Company;

use App\Clients\Resources\Matchmaker\Company\OpeningStatus;

class OpeningStatusFactory
{
    public function makeFromApiResult(object $data): OpeningStatus
    {
        return new OpeningStatus(
            $data->isClosed,
            $data->isClosedToday,
            $data->hasYetToOpenToday,
            $data->hasYetToCloseToday,
            $data->opensTodayAt,
            $data->closesTodayAt
        );
    }
}
