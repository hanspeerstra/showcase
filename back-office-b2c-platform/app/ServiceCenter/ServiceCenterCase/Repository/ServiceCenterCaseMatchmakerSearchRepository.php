<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Repository;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseMatchmakerSearch;
use App\Utils\Database\Contract\TransactionHandler;
use App\Utils\Storage\EloquentRepositoryHelperTrait;

class ServiceCenterCaseMatchmakerSearchRepository
{
    use EloquentRepositoryHelperTrait;

    /** @var TransactionHandler */
    private $transactionHandler;

    public function __construct(TransactionHandler $transactionHandler)
    {
        $this->transactionHandler = $transactionHandler;
    }

    /**
     * Stores changes only
     */
    public function persist(
        ServiceCenterCaseMatchmakerSearch $caseMatchmakerSearches
    ): ServiceCenterCaseMatchmakerSearch {
        return $this->transactionHandler->transactional(
            function () use ($caseMatchmakerSearches): ServiceCenterCaseMatchmakerSearch {
                /** @var ServiceCenterCaseMatchmakerSearch|null $currentCaseMatchmakerSearches */
                $currentCaseMatchmakerSearches = $caseMatchmakerSearches
                    ->getCase()
                    ->caseMatchmakerSearch()
                    ->first();

                if ($currentCaseMatchmakerSearches !== null) {
                    if ($currentCaseMatchmakerSearches->isSearchEqual($caseMatchmakerSearches)) {
                        return $currentCaseMatchmakerSearches;
                    }

                    self::doDelete($currentCaseMatchmakerSearches);
                }

                self::doInsert($caseMatchmakerSearches);

                return $caseMatchmakerSearches;
            }
        );
    }
}
