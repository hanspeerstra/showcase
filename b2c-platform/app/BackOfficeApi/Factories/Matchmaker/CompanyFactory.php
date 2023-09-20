<?php

declare(strict_types=1);

namespace App\Clients\Factories\Matchmaker;

use App\Clients\Factories\Matchmaker\Company\OpeningStatusFactory;
use App\Clients\Factories\QualityMarkFactory;
use App\Clients\Resources\Matchmaker\Company;

class CompanyFactory
{
    /** @var QualityMarkFactory */
    private $qualityMarkFactory;
    /** @var OpeningStatusFactory */
    private $openingStatusFactory;

    public function __construct(
        QualityMarkFactory $qualityMarkFactory,
        OpeningStatusFactory $openingStatusFactory
    ) {
        $this->qualityMarkFactory = $qualityMarkFactory;
        $this->openingStatusFactory = $openingStatusFactory;
    }

    public function makeFromApiResult(object $data): Company
    {
        return new Company(
            $data->id,
            $data->name,
            $data->usps,
            $data->description,
            $data->logo,
            array_map([$this->qualityMarkFactory, 'makeFromApiResult'], $data->qualityMarks),
            $data->kvk,
            $data->businessLocation,
            $data->openingStatus === null ? null : $this->openingStatusFactory->makeFromApiResult($data->openingStatus),
            $data->acceptsUrgentJobs,
            $data->promisedMinimumResponseTime,
            $data->rate,
            $data->reviewCount
        );
    }
}
