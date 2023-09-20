<?php

declare(strict_types=1);

namespace App\Synonyms\Overview\Http\Resource;

use App\Synonyms\SynonymInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SynonymInterface
 */
class SynonymResource extends JsonResource
{
    public function __construct(SynonymInterface $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'synonym' => $this->getSynonym(),
        ];
    }
}
