<?php

namespace App\Http\Api\Admin\Planning\Resources;

use Domains\Booking\ChangeoverDay;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChangeoverDay
 */
class ChangeoverDayResource extends JsonResource
{
    public function __construct(ChangeoverDay $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'date' => $this->date->format('Y-m-d'),
        ];
    }
}
