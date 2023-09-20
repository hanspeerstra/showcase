<?php

declare(strict_types=1);

namespace App\Synonyms\Repository;

use App\Models\Office\ProfessionSynonym;

class ProfessionSynonymRepository
{
    public function exists(ProfessionSynonym $professionSynonym): bool
    {
        return ProfessionSynonym::query()
            ->belongsToProfession($professionSynonym->getProfession())
            ->whereSynonym($professionSynonym->getSynonym())
            ->exists();
    }

    public function insert(ProfessionSynonym $professionSynonym): void
    {
        $professionSynonym->save();
    }
}
