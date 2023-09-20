<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseQueue\Repository;

use App\ServiceCenter\CaseQueue\CaseQueueEntry;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Utils\Storage\EloquentRepositoryHelperTrait;
use Illuminate\Support\Collection;

class CaseQueueRepository
{
    use EloquentRepositoryHelperTrait;

    /**
     * @return CaseQueueEntry[]
     */
    public function getAll(): iterable
    {
        // Interactive cases (ringing telephony cases) have highest prio
        $result = CaseQueueEntry::query()
            ->caseIsInteractive()
            ->byPriority()
            ->get();

        // Then all other non-interactive cases
        return $result->merge(
            CaseQueueEntry::query()
                ->caseIsPassive()
                ->byPriority()
                ->get()
        );
    }

    /**
     * @return CaseQueueEntry[]
     */
    public function getAllByWorkGroups(WorkGroup ...$workGroups): iterable
    {
        // Interactive cases (ringing telephony cases) have highest prio
        $result = CaseQueueEntry::query()
            ->hasWorkGroups(...$workGroups)
            ->caseIsInteractive()
            ->byPriority()
            ->get();

        // Then all other non-interactive cases
        return $result->merge(
            CaseQueueEntry::query()
                ->hasWorkGroups(...$workGroups)
                ->caseIsPassive()
                ->byPriority()
                ->get()
        );
    }

    /**
     * @return Collection|CaseQueueEntry[]
     */
    public function getAssignableInteractiveCasesByPrio(): iterable
    {
        return CaseQueueEntry::query()
            ->caseIsInteractive()
            ->hasAutomaticallyAssign()
            ->byPriority()
            ->get();
    }

    /**
     * @return Collection|CaseQueueEntry[]
     */
    public function getAssignablePassiveCasesByPrio(): iterable
    {
        return CaseQueueEntry::query()
            ->caseIsPassive()
            ->hasAutomaticallyAssign()
            ->byPriority()
            ->get();
    }

    public function getByCase(ServiceCenterCase $case): ?CaseQueueEntry
    {
        return CaseQueueEntry::query()
            ->where('case_id', '=', $case->getId())
            ->first();
    }

    public function insert(CaseQueueEntry $caseQueueEntry): CaseQueueEntry
    {
        self::doInsert($caseQueueEntry);

        return $caseQueueEntry;
    }

    public function delete(CaseQueueEntry $caseQueueEntry): void
    {
        self::doDelete($caseQueueEntry);
    }

    public function findInitialQueueEntryByCase(ServiceCenterCase $case): ?CaseQueueEntry
    {
        return CaseQueueEntry::query()
            ->withTrashed()
            ->where('case_id', '=', $case->getId())
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }
}
