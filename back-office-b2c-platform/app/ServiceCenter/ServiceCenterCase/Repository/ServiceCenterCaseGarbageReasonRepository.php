<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Repository;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseGarbageReason;
use App\Utils\Storage\EloquentRepositoryHelperTrait;

class ServiceCenterCaseGarbageReasonRepository
{
    use EloquentRepositoryHelperTrait;

    public function getById(int $garbageReasonId): ServiceCenterCaseGarbageReason
    {
        return ServiceCenterCaseGarbageReason::findOrFail($garbageReasonId);
    }

    /**
     * @param int[] $garbageReasonIdList
     * @return ServiceCenterCaseGarbageReason[]
     */
    public function getByIdList(int ...$garbageReasonIdList): iterable
    {
        return self::doGetByIdList(ServiceCenterCaseGarbageReason::class, $garbageReasonIdList);
    }

    public function getByLabel(string $label): ServiceCenterCaseGarbageReason
    {
        return ServiceCenterCaseGarbageReason::query()
            ->belongsToLabel($label)
            ->firstOrFail();
    }

    public function getAllReasonsForUsers(): iterable
    {
        return ServiceCenterCaseGarbageReason::query()
            ->isForUser()
            ->otherNamelyLast()
            ->get();
    }
}
