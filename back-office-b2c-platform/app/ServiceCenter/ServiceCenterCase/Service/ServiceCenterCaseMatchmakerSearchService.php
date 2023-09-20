<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseMatchmakerSearchRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseMatchmakerSearch;

class ServiceCenterCaseMatchmakerSearchService
{
    /** @var ServiceCenterCaseMatchmakerSearchRepository */
    private $caseMatchmakerSearchRepository;

    public function __construct(ServiceCenterCaseMatchmakerSearchRepository $caseMatchmakerSearchRepository)
    {
        $this->caseMatchmakerSearchRepository = $caseMatchmakerSearchRepository;
    }

    public function persist(ServiceCenterCaseMatchmakerSearch $caseMatchmakerSearch): ServiceCenterCaseMatchmakerSearch
    {
        if (!$caseMatchmakerSearch->getCase()->hasResult()) {
            $caseMatchmakerSearch = $this->caseMatchmakerSearchRepository->persist($caseMatchmakerSearch);
        }

        return $caseMatchmakerSearch;
    }
}
