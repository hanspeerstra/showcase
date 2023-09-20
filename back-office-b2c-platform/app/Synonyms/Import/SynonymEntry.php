<?php

declare(strict_types=1);

namespace App\Synonyms\Import;

class SynonymEntry
{
    private int $serviceTypeId;
    private array $synonyms;

    /**
     * @param string[] $synonyms
     */
    public function __construct(
        int $serviceTypeId,
        array $synonyms
    ) {
        $this->serviceTypeId = $serviceTypeId;
        $this->synonyms = $synonyms;
    }

    public function getServiceTypeId(): int
    {
        return $this->serviceTypeId;
    }

    public function getSynonyms(): array
    {
        return $this->synonyms;
    }
}
