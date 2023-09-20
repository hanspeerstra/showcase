<?php

namespace App\Http\Api\Admin\Resources;

use Domains\Booking\Models\Guest;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Guest
 */
class FisherResource extends JsonResource
{
    public function __construct(Guest $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'dateOfBirth' => $this->getDateOfBirth()?->format('Y-m-d'),
        ];
    }
}
