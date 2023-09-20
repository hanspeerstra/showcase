<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseSchedule\Repository;

use App\ServiceCenter\CaseSchedule\CaseScheduleEntry;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Utils\Storage\EloquentRepositoryHelperTrait;

class CaseScheduleRepository
{
    use EloquentRepositoryHelperTrait;

    public function insert(CaseScheduleEntry $entry): CaseScheduleEntry
    {
        self::doInsert($entry);

        return $entry;
    }

    public function delete(CaseScheduleEntry $entry): void
    {
        self::doDelete($entry);
    }

    public function findByCase(ServiceCenterCase $case): ?CaseScheduleEntry
    {
        return CaseScheduleEntry::query()
            ->whereCase($case)
            ->first();
    }
}
