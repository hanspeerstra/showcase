<?php

declare(strict_types=1);

namespace App\Clients\Resources;

use App\Clients\Resources\Matchmaker\Company;
use App\Clients\Resources\Matchmaker\MatchmakerCompanyFinderContactOption;
use JsonSerializable;

class MatchmakerCompanyFinderResult implements JsonSerializable
{
    /** @var Company */
    public $company;
    /** @var float|null */
    private $distance;
    private $contactOptions;

    /**
     * @param MatchmakerCompanyFinderContactOption[] $contactOptions
     */
    public function __construct(
        Company $company,
        ?float $distance,
        array $contactOptions
    ) {
        $this->company = $company;
        $this->distance = $distance;
        $this->contactOptions = $contactOptions;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function hasDistance(): bool
    {
        return $this->distance !== null;
    }

    public function getContactOptions(): array
    {
        return $this->contactOptions;
    }

    public function jsonSerialize(): array
    {
        return [
            'company' => $this->company,
            'distance' => $this->distance,
            'contactOptions' => $this->contactOptions,
        ];
    }
}
