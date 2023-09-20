<?php

declare(strict_types=1);

namespace App\Geo\PDOK;

use JsonSerializable;

class Address implements JsonSerializable
{
    /** @var string */
    private $postcode;
    /** @var int */
    private $houseNumber;
    /** @var string */
    private $houseNumberFull;
    /** @var string */
    private $street;
    /** @var string */
    private $city;
    /** @var string string */
    private $displayName;
    /** @var float|null */
    private $latitude;
    /** @var float|null */
    private $longitude;

    public function __construct(
        string $postcode,
        int $houseNumber,
        string $houseNumberFull,
        string $street,
        string $city,
        string $displayName,
        ?float $latitude,
        ?float $longitude
    ) {
        $this->postcode = $postcode;
        $this->houseNumber = $houseNumber;
        $this->houseNumberFull = $houseNumberFull;
        $this->street = $street;
        $this->city = $city;
        $this->displayName = $displayName;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function getPostcode(): string
    {
        return $this->postcode;
    }

    public function getHouseNumber(): int
    {
        return $this->houseNumber;
    }

    public function getHouseNumberFull(): string
    {
        return $this->houseNumberFull;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
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
            'postalCode' => $this->getPostcode(),
            'houseNumber' => $this->getHouseNumberFull(),
            'street' => $this->getStreet(),
            'city' => $this->getCity(),
            'formattedAddress' => $this->getDisplayName(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
