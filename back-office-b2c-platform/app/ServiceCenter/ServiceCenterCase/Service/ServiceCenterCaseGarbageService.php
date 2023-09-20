<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseGarbageReasonRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseGarbageReason;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseNote;
use App\Utils\Database\Contract\TransactionHandler;
use UnexpectedValueException;

class ServiceCenterCaseGarbageService
{
    /** @var ServiceCenterCaseService */
    private $serviceCenterCaseService;

    /** @var ServiceCenterCaseGarbageReasonRepository */
    private $garbageReasonRepository;

    /** @var TransactionHandler */
    private $transactionHandler;

    public function __construct(
        ServiceCenterCaseService $serviceCenterCaseService,
        ServiceCenterCaseGarbageReasonRepository $garbageReasonRepository,
        TransactionHandler $transactionHandler
    ) {
        $this->serviceCenterCaseService = $serviceCenterCaseService;
        $this->garbageReasonRepository = $garbageReasonRepository;
        $this->transactionHandler = $transactionHandler;
    }

    public function markAsGarbage(
        ServiceCenterCase $case,
        ServiceCenterCaseGarbageReason $garbageReason,
        ?ServiceCenterCaseNote $caseNote = null
    ): ServiceCenterCase {
        $case = $this->transactionHandler->transactional(function () use ($case, $caseNote, $garbageReason) {
            if ($caseNote !== null) {
                $this->serviceCenterCaseService->addCaseNote($caseNote);
            }

            return $this->makeCaseGarbage($case, $garbageReason);
        });

        return $case;
    }

    public function markAsGarbageClosedByForceCloseAgent(ServiceCenterCase $case): ServiceCenterCase
    {
        if ($case->isAssigned()) {
            throw new UnexpectedValueException(
                sprintf('Case (id: %s) should not have an assigned agent', $case->getId())
            );
        }

        $garbageReason = $this->garbageReasonRepository->getByLabel(
            ServiceCenterCaseGarbageReason::LABEL_CLOSED_BY_FORCE_CLOSE_AGENT
        );

        return $this->markAsGarbage(
            $case,
            $garbageReason
        );
    }

    public function markAsGarbageBySystemUser(ServiceCenterCase $case): ServiceCenterCase
    {
        if ($case->isAssigned()) {
            throw new UnexpectedValueException(
                sprintf('Case (id: %s) should not have an assigned agent', $case->getId())
            );
        }

        $garbageReason = $this->garbageReasonRepository->getByLabel(
            ServiceCenterCaseGarbageReason::LABEL_CLOSED_BY_SYSTEM_USER
        );

        return $this->markAsGarbage(
            $case,
            $garbageReason
        );
    }

    private function makeCaseGarbage(ServiceCenterCase $case, ServiceCenterCaseGarbageReason $garbageReason): ServiceCenterCase
    {
        $case = $this->serviceCenterCaseService->setCaseResult($case, null, null, $garbageReason);

        return $this->serviceCenterCaseService->closeCase($case);
    }
}
