<?php

declare(strict_types=1);

namespace App\Clients;

use App\Matchmaker\Location;
use InvalidArgumentException;

class MatchmakerCompanyFinderFilters
{
    /** @var string|null */
    private $professionSlug;
    /** @var string|null */
    private $serviceTypeSlug;
    /** @var Location|null */
    private $location;
    /** @var int|null */
    private $distance;
    /** @var int|null */
    private $minimumReviewRating;
    /** @var bool */
    private $acceptsUrgentJobs;
    /** @var @var bool */
    private $excludeOutsideWorkArea;
    private $brands;
    private $qualityMarks;

    /**
     * @param string[] $brands
     * @param string[] $qualityMarks
     */
    public function __construct(
        ?string $professionSlug,
        ?string $serviceTypeSlug,
        ?Location $location,
        ?int $distance,
        ?int $minimumReviewRating,
        bool $acceptsUrgentJobs,
        array $brands,
        array $qualityMarks,
        bool $excludeOutsideWorkArea = false
    ) {
        if ($minimumReviewRating === 0) {
            throw new InvalidArgumentException('Minimum review rating must be greater than zero.');
        }

        if ($distance === 0) {
            throw new InvalidArgumentException('Distance must be greater than zero.');
        }

        $this->professionSlug = $professionSlug;
        $this->serviceTypeSlug = $serviceTypeSlug;
        $this->location = $location;
        $this->distance = $distance;
        $this->minimumReviewRating = $minimumReviewRating;
        $this->acceptsUrgentJobs = $acceptsUrgentJobs;
        $this->brands = $brands;
        $this->qualityMarks = $qualityMarks;
        $this->excludeOutsideWorkArea = $excludeOutsideWorkArea;
    }

    public function getProfessionSlug(): ?string
    {
        return $this->professionSlug;
    }

    public function getServiceTypeSlug(): ?string
    {
        return $this->serviceTypeSlug;
    }

    public function hasServiceTypeSlug(): bool
    {
        return $this->serviceTypeSlug !== null;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function getDistance(): ?int
    {
        return $this->distance;
    }

    public function hasDistance(): bool
    {
        return $this->distance > 0;
    }

    public function getMinimumReviewRating(): ?int
    {
        return $this->minimumReviewRating;
    }

    public function hasMinimumReviewRating(): bool
    {
        return $this->minimumReviewRating > 0;
    }

    public function acceptsUrgentJobs(): bool
    {
        return $this->acceptsUrgentJobs;
    }

    public function getBrands(): array
    {
        return $this->brands;
    }

    public function hasBrands(): bool
    {
        return count($this->brands) > 0;
    }

    public function getQualityMarks(): array
    {
        return $this->qualityMarks;
    }

    public function hasQualityMarks(): bool
    {
        return count($this->qualityMarks) > 0;
    }

    public function excludeOutsideWorkArea(): bool
    {
        return $this->excludeOutsideWorkArea;
    }

    public function hasReferencePoint(): bool
    {
        return !$this->hasNoReferencePoint();
    }

    public function hasNoReferencePoint(): bool
    {
        return $this->location === null || $this->location->hasNoCoordinates();
    }
}
