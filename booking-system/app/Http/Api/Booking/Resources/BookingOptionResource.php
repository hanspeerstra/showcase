<?php

namespace App\Http\Api\Booking\Resources;

use App\Http\Api\Admin\Resources\ProductResource;
use Domains\Booking\BookingOption;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingOption
 */
class BookingOptionResource extends JsonResource
{
    public function __construct(BookingOption $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [

            'product' => new ProductResource($this->product),
            'available' => $this->available,
        ];
    }
}
