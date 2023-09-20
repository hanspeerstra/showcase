<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Service;

use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseGarbageService;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\Utils\Database\Contract\TransactionHandler;
use Carbon\Carbon;

class AgentSessionForceEndSessionService
{
    /** @var AgentSessionService */
    private $agentSessionService;

    /** @var ServiceCenterCaseService */
    private $serviceCenterCaseService;

    /** @var ServiceCenterCaseRepository */
    private $serviceCenterCaseRepository;

    /** @var ServiceCenterCaseGarbageService */
    private $serviceCenterCaseGarbageService;

    /** @var TransactionHandler */
    private $transactionHandler;

    public function __construct(
        AgentSessionService $agentSessionService,
        ServiceCenterCaseService $serviceCenterCaseService,
        ServiceCenterCaseRepository $serviceCenterCaseRepository,
        ServiceCenterCaseGarbageService $serviceCenterCaseGarbageService,
        TransactionHandler $transactionHandler
    ) {
        $this->agentSessionService = $agentSessionService;
        $this->serviceCenterCaseService = $serviceCenterCaseService;
        $this->serviceCenterCaseRepository = $serviceCenterCaseRepository;
        $this->serviceCenterCaseGarbageService = $serviceCenterCaseGarbageService;
        $this->transactionHandler = $transactionHandler;
    }

    public function forceEndSession(AgentSession $agentSession): void
    {
        $assignedCases = $this->serviceCenterCaseRepository->findCasesByAssignedAgent($agentSession->getUser());

        $this->transactionHandler->transactional(function () use ($assignedCases, $agentSession) {
            foreach ($assignedCases as $case) {
                $sourceTelephonySession = $case->getSourceTelephonySession();

                if ($sourceTelephonySession === null) {
                    $this->serviceCenterCaseService->unassignAgentFromCaseForAgentSession($case, $agentSession);

                    continue;
                }

                if ($sourceTelephonySession->isActive()) {
                    $sourceTelephonySession->ended_at = Carbon::now();
                    $sourceTelephonySession->save();
                    $case = $case->refresh();
                }

                if (!$case->hasResult()) {
                    $this->serviceCenterCaseGarbageService->markAsGarbageClosedByForceCloseAgent($case);
                } else {
                    $this->serviceCenterCaseService->closeCase($case);
                }
            }

            $this->agentSessionService->endSession($agentSession);
        });
    }
}
