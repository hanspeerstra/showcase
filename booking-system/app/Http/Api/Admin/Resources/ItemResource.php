<?php

namespace App\Http\Api\Admin\Resources;

use Domains\Booking\Models\Item;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Item
 */
class ItemResource extends JsonResource
{
    public function __construct(Item $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'occupancy' => $this->getOccupancy(),
            'capacity' => $this->getCapacity(),
            'pricePerCapacity' => $this->getPricePerCapacity(),
            'price' => $this->getPrice(),
            'period' => $this->getPeriod(),
            'product' => new ProductResource($this->getProduct())
        ];
    }
}
