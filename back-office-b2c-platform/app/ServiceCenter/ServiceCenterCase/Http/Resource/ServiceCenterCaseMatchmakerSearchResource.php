<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Resource;

use App\Http\Resources\Mygo\ServiceTypeResource;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseMatchmakerSearch;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceCenterCaseMatchmakerSearch
 */
class ServiceCenterCaseMatchmakerSearchResource extends JsonResource
{
    public function __construct(ServiceCenterCaseMatchmakerSearch $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @inheritdoc
     */
    public function toArray($request): array
    {
        return [
            'searchMethod' => $this->getSearchMethod(),
            'resultCount' => $this->getResultCount(),
            'serviceType' => $this->getServicetype() !== null ? new ServiceTypeResource($this->getServicetype()) : null,
            'postcode' => $this->getPostcode(),
            'houseNumber' => $this->getHouseNumber(),
            'createdAt' => $this->getCreatedAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
