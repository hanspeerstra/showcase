<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Resource;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseGarbageReason;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceCenterCaseGarbageReason
 */
class ServiceCenterCaseGarbageReasonResource extends JsonResource
{
    public function __construct(ServiceCenterCaseGarbageReason $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request)
    {
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'label' => $this->getLabel(),
            'name' => $this->getName(),
            'createdAt' => $this->getCreatedAt()->format(DateTimeInterface::ATOM),
            'updatedAt' => $this->getUpdatedAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
