<?php

declare(strict_types=1);

namespace App\Clients\Resources;

use JsonSerializable;

class City implements JsonSerializable
{
    /** @var int */
    private $id;
    /** @var string */
    private $slug;
    /** @var string */
    private $name;
    /** @var float|null */
    private $latitude;
    /** @var float|null */
    private $longitude;

    public function __construct(
        int $id,
        string $slug,
        string $name,
        ?float $latitude,
        ?float $longitude
    ) {
        $this->id = $id;
        $this->slug = $slug;
        $this->name = $name;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
