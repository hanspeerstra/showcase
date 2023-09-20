<?php

namespace App\Http\Api\Admin\Resources;

use Domains\Booking\Models\Address;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 */
class AddressResource extends JsonResource
{
    public function __construct(Address $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'name' => $this->getName(),
            'phoneNumber' => $this->getPhoneNumber(),
            'street' => $this->getStreet(),
            'houseNumber' => $this->getHouseNumber(),
            'postalCode' => $this->getPostalCode(),
            'city' => $this->getCity(),
            'country' => $this->getCountry() === null ? null : new CountryResource($this->getCountry()),
        ];
    }
}
