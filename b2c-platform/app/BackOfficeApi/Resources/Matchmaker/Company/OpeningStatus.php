<?php

declare(strict_types=1);

namespace App\Clients\Resources\Matchmaker\Company;

use JsonSerializable;

class OpeningStatus implements JsonSerializable
{
    /** @var bool */
    private $closed;
    /** @var bool */
    private $closedToday;
    /** @var bool */
    private $yetToOpenToday;
    /** @var bool */
    private $yetToCloseToday;
    /** @var string */
    private $opensTodayAt;
    /** @var string */
    private $closesTodayAt;

    public function __construct(
        bool $closed,
        bool $closedToday,
        bool $yetToOpenToday,
        bool $yetToCloseToday,
        ?string $opensTodayAt,
        ?string $closesTodayAt
    ) {
        $this->closed = $closed;
        $this->closedToday = $closedToday;
        $this->yetToOpenToday = $yetToOpenToday;
        $this->opensTodayAt = $opensTodayAt;
        $this->yetToCloseToday = $yetToCloseToday;
        $this->closesTodayAt = $closesTodayAt;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function hasYetToCloseToday(): bool
    {
        return $this->yetToCloseToday;
    }

    public function getClosesTodayAt(): ?string
    {
        return $this->closesTodayAt;
    }

    public function jsonSerialize(): array
    {
        return [
            'isClosed' => $this->closed,
            'isClosedToday' => $this->closedToday,
            'hasYetToOpenToday' => $this->yetToOpenToday,
            'hasYetToCloseToday' => $this->yetToCloseToday,
            'opensTodayAt' => $this->opensTodayAt,
            'closesTodayAt' => $this->closesTodayAt,
        ];
    }
}
