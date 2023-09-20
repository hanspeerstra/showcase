<?php

namespace App\Http\Api\Booking\Resources;

use Domains\Booking\Availability;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Availability
 */
class AvailabilityResource extends JsonResource
{
    public function __construct(Availability $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'maxPersons' => $this->maxPersons,
            'options' => BookingOptionResource::collection($this->bookingOptions),
        ];
    }
}
