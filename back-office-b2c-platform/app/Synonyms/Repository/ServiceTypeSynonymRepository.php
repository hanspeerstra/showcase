<?php

declare(strict_types=1);

namespace App\Synonyms\Repository;

use App\Models\Office\ServicetypeSynonym;

class ServiceTypeSynonymRepository
{
    public function exists(ServicetypeSynonym $servicetypeSynonym): bool
    {
        return ServicetypeSynonym::query()
            ->belongsToServiceType($servicetypeSynonym->getServiceType())
            ->whereSynonym($servicetypeSynonym->getSynonym())
            ->exists();
    }

    public function insert(ServicetypeSynonym $serviceTypeSynonym): void
    {
        $serviceTypeSynonym->save();
    }
}
