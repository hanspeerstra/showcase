<?php

declare(strict_types=1);

namespace App\Clients;

class MatchmakerCompanyFinderParams
{
    /** @var int */
    private $page;
    /** @var int */
    private $perPage = 5;
    /** @var MatchmakerCompanyFinderFilters */
    private $filters;
    /** @var string */
    private $sort;

    public function __construct(
        int $page,
        MatchmakerCompanyFinderFilters $filters,
        string $sort
    ) {
        $this->page = $page;
        $this->filters = $filters;
        $this->sort = $sort;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getFilters(): MatchmakerCompanyFinderFilters
    {
        return $this->filters;
    }

    public function getSort(): string
    {
        return $this->sort;
    }
}
