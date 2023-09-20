<?php

namespace App\Http\Api\Admin\Resources;

use Domains\Product\Models\Product;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public function __construct(Product $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'name' => $this->getName(),
            'capacity' => $this->getCapacity(),
        ];
    }
}
