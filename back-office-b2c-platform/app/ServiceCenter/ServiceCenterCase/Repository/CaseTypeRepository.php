<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Repository;

use App\ServiceCenter\ServiceCenterCase\CaseType;
use App\Utils\Storage\AbstractDynamicRepository;

/**
 * @method CaseTypeQueryBuilder query()
 */
class CaseTypeRepository extends AbstractDynamicRepository
{
    public function __construct()
    {
        parent::__construct(CaseType::class, CaseTypeQueryBuilder::class);
    }

    public function getByLabel(string $label): CaseType
    {
        return $this->query()->whereLabel($label)->firstOrFail();
    }

    public function persist(CaseType $caseType): void
    {
        self::doPersist($caseType);
    }
}
