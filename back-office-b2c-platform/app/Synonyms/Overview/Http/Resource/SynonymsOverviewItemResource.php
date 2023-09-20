<?php

declare(strict_types=1);

namespace App\Synonyms\Overview\Http\Resource;

use App\Synonyms\Overview\Model\SynonymsOverviewItem;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SynonymsOverviewItem
 */
class SynonymsOverviewItemResource extends JsonResource
{
    public function __construct(SynonymsOverviewItem $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'serviceType' => $this->getServiceType() === null
                ? null
                : new ServiceTypeResource($this->getServiceType()),
            'profession' => $this->getProfession() === null
                ? null
                : new ProfessionResource($this->getProfession()),
            'updatedAt' => $this->getUpdatedAt() === null
                ? null
                : $this->getUpdatedAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
