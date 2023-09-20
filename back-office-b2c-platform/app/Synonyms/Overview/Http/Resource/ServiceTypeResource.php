<?php

declare(strict_types=1);

namespace App\Synonyms\Overview\Http\Resource;

use App\Models\Office\Servicetype;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Servicetype
 */
class ServiceTypeResource extends JsonResource
{
    public function __construct(Servicetype $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @inheritdoc
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'synonyms' => SynonymResource::collection(
                $this->getServicetypeSynonyms()
            ),
            'matchmaker' => $this->isAllowedOnMatchmaker(),
            'createdAt' => $this->getCreatedAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
