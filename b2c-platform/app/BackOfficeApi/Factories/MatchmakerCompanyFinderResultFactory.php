<?php

declare(strict_types=1);

namespace App\Clients\Factories;

use App\Clients\Factories\Matchmaker\CompanyFactory;
use App\Clients\Factories\Matchmaker\MatchmakerCompanyFinderContactOptionFactory;
use App\Clients\Resources\MatchmakerCompanyFinderResult;

class MatchmakerCompanyFinderResultFactory
{
    /** @var CompanyFactory */
    private $companyFactory;
    /** @var MatchmakerCompanyFinderContactOptionFactory */
    private $matchmakerCompanyFinderContactOptionFactory;

    public function __construct(
        CompanyFactory $companyFactory,
        MatchmakerCompanyFinderContactOptionFactory $matchmakerCompanyFinderContactOptionFactory
    ) {
        $this->companyFactory = $companyFactory;
        $this->matchmakerCompanyFinderContactOptionFactory = $matchmakerCompanyFinderContactOptionFactory;
    }

    public function makeFromApiResult(object $data): MatchmakerCompanyFinderResult
    {
        return new MatchmakerCompanyFinderResult(
            $this->companyFactory->makeFromApiResult($data->company),
            $data->distance,
            array_map([$this->matchmakerCompanyFinderContactOptionFactory, 'makeFromApiResult'], $data->contactOptions),
        );
    }
}
