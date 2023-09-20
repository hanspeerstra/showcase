<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseSchedule\Service;

use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\CaseQueue\Service\CaseQueueService;
use App\ServiceCenter\CaseSchedule\CaseScheduleEntry;
use App\ServiceCenter\CaseSchedule\Exception\CaseNotScheduledException;
use App\ServiceCenter\CaseSchedule\Repository\CaseScheduleRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Utils\Database\Contract\TransactionHandler;
use Carbon\CarbonInterface;

class CaseScheduleService
{
    /** @var CaseScheduleRepository */
    private $caseScheduleRepository;

    /** @var ServiceCenterCaseService */
    private $caseService;

    /** @var CaseQueueService */
    private $caseQueueService;

    /** @var TransactionHandler */
    private $transactionHandler;

    public function __construct(
        CaseScheduleRepository $caseScheduleRepository,
        ServiceCenterCaseService $caseService,
        CaseQueueService $caseQueueService,
        TransactionHandler $transactionHandler
    ) {
        $this->caseScheduleRepository = $caseScheduleRepository;
        $this->caseService = $caseService;
        $this->caseQueueService = $caseQueueService;
        $this->transactionHandler = $transactionHandler;
    }

    public function scheduleCase(ServiceCenterCase $case, CarbonInterface $dueAt): CaseScheduleEntry
    {
        $caseScheduleEntry = CaseScheduleEntry::makeInstance($case, $dueAt);

        return $this->caseScheduleRepository->insert($caseScheduleEntry);
    }

    /**
     * @throws CaseNotScheduledException
     */
    public function rescheduleCase(ServiceCenterCase $case, CarbonInterface $dueAt): CaseScheduleEntry
    {
        $currentScheduleEntry = $this->caseScheduleRepository->findByCase($case);

        if (null === $currentScheduleEntry) {
            throw CaseNotScheduledException::forCase($case);
        }

        $newScheduleEntry = CaseScheduleEntry::makeInstance($case, $dueAt);

        return $this->transactionHandler->transactional(function () use ($currentScheduleEntry, $newScheduleEntry) {
            $this->caseScheduleRepository->delete($currentScheduleEntry);

            return $this->caseScheduleRepository->insert($newScheduleEntry);
        });
    }

    /**
     * @throws CaseNotScheduledException
     */
    public function assignScheduledCase(ServiceCenterCase $case, AgentSession $agentSession): void
    {
        $currentScheduleEntry = $this->caseScheduleRepository->findByCase($case);

        if (null === $currentScheduleEntry) {
            throw CaseNotScheduledException::forCase($case);
        }

        $this->transactionHandler->transactional(function () use ($case, $agentSession, $currentScheduleEntry) {
            $this->caseScheduleRepository->delete($currentScheduleEntry);

            $this->caseService->startCase($case, $agentSession);
        });
    }

    /**
     * @throws CaseNotScheduledException
     */
    public function queueScheduledCase(ServiceCenterCase $case): void
    {
        $currentScheduleEntry = $this->caseScheduleRepository->findByCase($case);

        if (null === $currentScheduleEntry) {
            throw CaseNotScheduledException::forCase($case);
        }

        $this->transactionHandler->transactional(function () use ($case, $currentScheduleEntry) {
            $this->caseScheduleRepository->delete($currentScheduleEntry);

            $this->caseQueueService->enqueue($case);
        });
    }

    public function isScheduled(ServiceCenterCase $case): bool
    {
        return null !== $this->caseScheduleRepository->findByCase($case);
    }
}
