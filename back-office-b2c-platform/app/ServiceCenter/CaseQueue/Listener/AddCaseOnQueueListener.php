<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseQueue\Listener;

use App\ServiceCenter\CaseQueue\Service\CaseQueueService;
use App\ServiceCenter\CaseSchedule\Service\CaseScheduleService;
use App\ServiceCenter\ServiceCenterCase\Event\CaseCreatedEvent;
use App\ServiceCenter\ServiceCenterCase\Event\CaseTypeChangedEvent;
use App\ServiceCenter\ServiceCenterCase\Event\CaseWasUnassignedEvent;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;

class AddCaseOnQueueListener
{
    /** @var CaseQueueService */
    private $caseQueueService;
    /** @var ServiceCenterCaseRepository */
    private $serviceCenterCaseRepository;
    /** @var CaseScheduleService */
    private $caseScheduleService;

    public function __construct(
        CaseQueueService $caseQueueService,
        ServiceCenterCaseRepository $serviceCenterCaseRepository,
        CaseScheduleService $caseScheduleService
    ) {
        $this->caseQueueService = $caseQueueService;
        $this->serviceCenterCaseRepository = $serviceCenterCaseRepository;
        $this->caseScheduleService = $caseScheduleService;
    }

    public function onCaseCreated(CaseCreatedEvent $event): void
    {
        if (!$event->getCase()->isAssigned()) {
            if ($this->caseScheduleService->isScheduled($event->getCase())) {
                return;
            }

            $this->caseQueueService->enqueue($event->getCase());
        }
    }

    public function onCaseTypeChanged(CaseTypeChangedEvent $event): void
    {
        $case = $event->getCase();

        if (!$case->isAssigned() && !$this->caseQueueService->isQueued($case)) {
            if ($this->caseScheduleService->isScheduled($case)) {
                return;
            }

            $this->caseQueueService->enqueue($case);
        }
    }

    public function onCaseWasUnassigned(CaseWasUnassignedEvent $event): void
    {
        $case = $this->serviceCenterCaseRepository->refresh($event->getCase());

        if (!$case->isClosed()) {
            if ($this->caseScheduleService->isScheduled($case)) {
                return;
            }

            $this->caseQueueService->enqueue($event->getCase());
        }
    }
}
