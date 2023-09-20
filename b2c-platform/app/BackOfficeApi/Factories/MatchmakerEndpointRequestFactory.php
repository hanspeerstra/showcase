<?php

declare(strict_types=1);

namespace App\Clients\Factories;

use App\Clients\MatchmakerCompanyFinderParams;
use App\Clients\OfficeApiClient;
use GuzzleHttp\Psr7\Request;

class MatchmakerEndpointRequestFactory
{
    public function makeCompanyFinderRequest(MatchmakerCompanyFinderParams $params): Request
    {
        $page = $params->getPage();
        $perPage = $params->getPerPage();
        $filters = $params->getFilters();
        $sort = $params->getSort();

        return new Request(
            'get',
            '/matchmaker/company-finder/results',
            OfficeApiClient::JSON_HEADERS,
            json_encode(
                [
                    'page' => $page,
                    'perPage' => $perPage,
                    'professionSlug' => $filters->getProfessionSlug(),
                    'serviceTypeSlug' => $filters->getServiceTypeSlug(),
                    'regionId' => $filters->getLocation() === null || $filters->getLocation()->getRegion() === null
                        ? null
                        : $filters->getLocation()->getRegion()->getId(),
                    'referencePoint' => $filters->getLocation() === null || $filters->getLocation()->getLatitude() === null || $filters->getLocation()->getLongitude() === null
                        ? null
                        : ['latitude' => $filters->getLocation()->getLatitude(), 'longitude' => $filters->getLocation()->getLongitude()],
                    'distanceInMeters' => $filters->getDistance() === null
                        ? null
                        : $filters->getDistance() * 1000,
                    'minimumReviewRating' => $filters->getMinimumReviewRating(),
                    'acceptsUrgentJobs' => $filters->acceptsUrgentJobs(),
                    'brands' => implode(',', $filters->getBrands()),
                    'qualityMarks' => implode(',', $filters->getQualityMarks()),
                    'excludeOutsideWorkArea' => $filters->excludeOutsideWorkArea(),
                    'sort' => $sort,
                ]
            )
        );
    }
}
