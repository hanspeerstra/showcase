<?php

declare(strict_types=1);

namespace App\Matchmaker;

use App\Clients\Resources\City;
use App\Clients\Resources\Region;
use App\Geo\PDOK\Address;
use JsonSerializable;

class Location implements JsonSerializable
{
    /** @var object|null */
    private $province;
    /** @var Region|null */
    private $region;
    /** @var City|null */
    private $city;
    /** @var Address|null */
    private $address;
    /** @var string|null */
    private $postalCode;
    /** @var string|null */
    private $houseNumber;

    public function __construct(
        ?object $province,
        ?Region $region,
        ?City $city = null,
        ?Address $address = null,
        ?string $postalCode = '',
        ?string $houseNumber = ''
    ) {
        $this->province = $province;
        $this->region = $region;
        $this->city = $city;
        $this->address = $address;
        $this->postalCode = $postalCode;
        $this->houseNumber = $houseNumber;
    }

    public function getProvince(): ?object
    {
        return $this->province;
    }

    public function getRegion(): ?Region
    {
        return $this->region;
    }

    public function hasRegion(): bool
    {
        return $this->getRegion() !== null;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function getHouseNumber(): ?string
    {
        return $this->houseNumber;
    }

    public function getLatitude(): ?float
    {
        if ($this->address !== null) {
            return $this->address->getLatitude();
        } elseif ($this->city !== null) {
            return $this->city->getLatitude();
        }

        return null;
    }

    public function getLongitude(): ?float
    {
        if ($this->address !== null) {
            return $this->address->getLongitude();
        } elseif ($this->city !== null) {
            return $this->city->getLongitude();
        }

        return null;
    }

    public function hasCoordinates(): bool
    {
        return !$this->hasNoCoordinates();
    }

    public function hasNoCoordinates(): bool
    {
        return $this->getLatitude() === null || $this->getLongitude() === null;
    }

    public function jsonSerialize(): array
    {
        return [
            'region' => $this->region,
            'city' => $this->city,
            'address' => $this->address,
            'postalCode' => $this->postalCode,
            'houseNumber' => $this->houseNumber,
        ];
    }
}
