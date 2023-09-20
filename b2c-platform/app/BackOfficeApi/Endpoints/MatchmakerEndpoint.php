<?php

declare(strict_types=1);

namespace App\BackOfficeApi\Endpoints;

use App\Clients\Factories\CityFactory;
use App\Clients\Factories\MatchmakerCompanyFinderResultFactory;
use App\Clients\Factories\MatchmakerEndpointRequestFactory;
use App\Clients\MatchmakerCompanyFinderParams;
use App\Clients\MatchmakerCompanyFinderResponse;
use App\Clients\OfficeApiClient;
use App\Clients\Resources\MatchmakerCompanyFinderResult;

class MatchmakerEndpoint implements MatchmakerEndpointInterface
{
    /** @var OfficeApiClient */
    private $officeApiClient;
    /** @var MatchmakerEndpointRequestFactory */
    private $matchmakerEndpointRequestFactory;
    /** @var MatchmakerCompanyFinderResultFactory */
    private $matchmakerCompanyFinderResultFactory;
    /** @var CityFactory */
    private $cityFactory;

    public function __construct(
        OfficeApiClient $officeApiClient,
        MatchmakerEndpointRequestFactory $matchmakerEndpointRequestFactory,
        MatchmakerCompanyFinderResultFactory $matchmakerCompanyFinderResultFactory,
        CityFactory $cityFactory
    ) {
        $this->officeApiClient = $officeApiClient;
        $this->matchmakerEndpointRequestFactory = $matchmakerEndpointRequestFactory;
        $this->matchmakerCompanyFinderResultFactory = $matchmakerCompanyFinderResultFactory;
        $this->cityFactory = $cityFactory;
    }

    public function getCompanyFinder(MatchmakerCompanyFinderParams $params): MatchmakerCompanyFinderResponse
    {
        $response = $this->officeApiClient->sendHttpRequest(
            $this->matchmakerEndpointRequestFactory->makeCompanyFinderRequest($params)
        );

        $contents = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);

        return new MatchmakerCompanyFinderResponse(
            array_map(
                function (object $scoredMatchmakerCompanyFinderResult): MatchmakerCompanyFinderResult {
                    return $this->matchmakerCompanyFinderResultFactory->makeFromApiResult(
                        $scoredMatchmakerCompanyFinderResult->matchmakerResult
                    );
                },
                $contents->data
            ),
            $contents->nearbyCity === null ? null : $this->cityFactory->makeFromApiResult($contents->nearbyCity),
            $contents->page,
            $contents->perPage,
            $contents->lastPage,
            $contents->count
        );
    }
}
