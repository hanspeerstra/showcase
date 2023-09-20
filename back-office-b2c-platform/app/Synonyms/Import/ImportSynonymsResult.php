<?php

declare(strict_types=1);

namespace App\Synonyms\Import;

class ImportSynonymsResult
{
    private int $insertCount;
    private int $existCount;

    public function __construct(int $insertCount, int $existCount)
    {
        $this->insertCount = $insertCount;
        $this->existCount = $existCount;
    }

    public function getInsertCount(): int
    {
        return $this->insertCount;
    }

    public function getExistCount(): int
    {
        return $this->existCount;
    }
}
