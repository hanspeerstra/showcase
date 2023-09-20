<?php

namespace App\Http\Api\Admin\Resources;

use Domains\Customer\Models\Customer;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    public function __construct(Customer $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'email' => $this->getEmailAddress(),
        ];
    }
}
