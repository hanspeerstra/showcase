<?php

declare(strict_types=1);

namespace App\Clients;

use App\Clients\Resources\Matchmaker\City;
use App\Clients\Resources\MatchmakerCompanyFinderResult;

class MatchmakerCompanyFinderResponse
{
    private $results;
    /** @var City|null */
    private $city;
    /** @var int */
    private $page;
    /** @var int */
    private $perPage;
    /** @var int */
    private $lastPage;
    /** @var int */
    private $count;

    /**
     * @param MatchmakerCompanyFinderResult[] $results
     */
    public function __construct(
        array $results,
        ?City $city,
        int $page,
        int $perPage,
        int $lastPage,
        int $count
    ) {
        $this->results = $results;
        $this->city = $city;
        $this->page = $page;
        $this->perPage = $perPage;
        $this->lastPage = $lastPage;
        $this->count = $count;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
