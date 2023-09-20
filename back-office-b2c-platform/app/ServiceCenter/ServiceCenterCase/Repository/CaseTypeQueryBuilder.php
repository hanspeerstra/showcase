<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Repository;

use App\ServiceCenter\ServiceCenterCase\CaseType;
use App\Utils\Storage\AbstractQueryBuilder;

/**
 * Type-specific query builder used by {@see CaseTypeRepository}
 *
 * @method CaseType|null first();
 * @method CaseType firstOrFail();
 * @method CaseType[] get();
 */
class CaseTypeQueryBuilder extends AbstractQueryBuilder
{
    public function whereLabel(string $label): self
    {
        $this->builder->where(CaseType::COL_LABEL, $label);
        return $this;
    }
}
