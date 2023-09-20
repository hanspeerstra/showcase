<?php

declare(strict_types=1);

namespace App\BackOfficeApi\Endpoints;

use App\Clients\MatchmakerCompanyFinderParams;
use App\Clients\MatchmakerCompanyFinderResponse;

interface MatchmakerEndpointInterface
{
    public function getCompanyFinder(MatchmakerCompanyFinderParams $params): MatchmakerCompanyFinderResponse;
}
