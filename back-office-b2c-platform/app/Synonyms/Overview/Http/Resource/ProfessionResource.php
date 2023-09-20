<?php

declare(strict_types=1);

namespace App\Synonyms\Overview\Http\Resource;

use App\Models\Office\Profession;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Profession
 */
class ProfessionResource extends JsonResource
{
    public function __construct(Profession $resource)
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
                $this->getProfessionSynonyms()
            ),
            'matchmaker' => $this->isMatchmaker(),
            'createdAt' => $this->getCreatedAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
