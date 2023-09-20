<?php

declare(strict_types=1);

namespace App\Clients\Resources\Matchmaker;

use App\Clients\Resources\AbstractCompany;
use App\Clients\Resources\Matchmaker\Company\OpeningStatus;
use App\Clients\Resources\QualityMark;
use JsonSerializable;

class Company extends AbstractCompany implements JsonSerializable
{
    /** @var int */
    private $id;
    /** @var string[] */
    private $usps;
    /** @var int|null */
    private $description;
    /** @var QualityMark[] */
    private $qualityMarks;
    /** @var string|null */
    private $kvk;
    /** @var string|null */
    private $businessLocation;
    /** @var OpeningStatus|null */
    private $openingStatus;
    /** @var bool|null */
    private $acceptsUrgentJobs;
    /** @var int|null */
    private $promisedMinimumResponseTime;
    /** @var float|null */
    private $rate;
    /** @var int */
    private $reviewCount;
    /** @var string|null */
    public $companyPageAbsoluteUrl;

    /**
     * @param string[] $usps
     * @param QualityMark[] $qualityMarks
     */
    public function __construct(
        int $id,
        string $name,
        array $usps,
        ?string $description,
        ?string $logo,
        array $qualityMarks,
        ?string $kvk,
        ?string $businessLocation,
        ?OpeningStatus $openingStatus,
        ?bool $acceptsUrgentJobs,
        ?int $promisedMinimumResponseTime,
        ?float $rate,
        int $reviewCount,
        string $companyPageAbsoluteUrl = null
    ) {
        parent::__construct($name, $logo);

        $this->id = $id;
        $this->usps = $usps;
        $this->description = $description;
        $this->qualityMarks = $qualityMarks;
        $this->kvk = $kvk;
        $this->businessLocation = $businessLocation;
        $this->openingStatus = $openingStatus;
        $this->acceptsUrgentJobs = $acceptsUrgentJobs;
        $this->promisedMinimumResponseTime = $promisedMinimumResponseTime;
        $this->rate = $rate;
        $this->reviewCount = $reviewCount;
        $this->companyPageAbsoluteUrl = $companyPageAbsoluteUrl;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsps(): array
    {
        return $this->usps;
    }

    public function getDescription(): ?int
    {
        return $this->description;
    }

    public function getQualityMarks(): array
    {
        return $this->qualityMarks;
    }

    public function getKvk(): ?string
    {
        return $this->kvk;
    }

    public function getBusinessLocation(): ?string
    {
        return $this->businessLocation;
    }

    public function getOpeningStatus(): ?OpeningStatus
    {
        return $this->openingStatus;
    }

    public function getPromisedMinimumResponseTime(): ?int
    {
        return $this->promisedMinimumResponseTime;
    }

    public function hasPromisedMinimumResponseTime(): bool
    {
        return $this->promisedMinimumResponseTime > 0;
    }

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function getReviewCount(): int
    {
        return $this->reviewCount;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'usps' => $this->usps,
            'description' => $this->description,
            'logo' => $this->logo,
            'qualityMarks' => $this->qualityMarks,
            'kvk' => $this->kvk,
            'businessLocation' => $this->businessLocation,
            'openingStatus' => $this->openingStatus,
            'acceptsUrgentJobs' => $this->acceptsUrgentJobs,
            'promisedMinimumResponseTime' => $this->promisedMinimumResponseTime,
            'rate' => $this->rate,
            'reviewCount' => $this->reviewCount,
            'companyPageAbsoluteUrl' => $this->companyPageAbsoluteUrl,
        ];
    }
}
