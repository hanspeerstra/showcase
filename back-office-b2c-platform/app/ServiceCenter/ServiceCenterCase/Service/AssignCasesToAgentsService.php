<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\Auth\User;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\AgentSession\AgentSessionStatus;
use App\ServiceCenter\AgentSession\Repository\AgentSessionLogRepository;
use App\ServiceCenter\AgentSession\Repository\AgentSessionRepository;
use App\ServiceCenter\CaseQueue\CaseQueueEntry;
use App\ServiceCenter\CaseQueue\Repository\CaseQueueRepository;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Utils\Database\Contract\TransactionHandler;

class AssignCasesToAgentsService
{
    private CaseQueueRepository $caseQueueRepository;
    private AgentSessionRepository $agentSessionRepository;
    private AgentSessionLogRepository $agentSessionLogRepository;
    private ServiceCenterCaseRepository $caseRepository;
    private ServiceCenterCaseService $serviceCenterCaseService;
    private TransactionHandler $transactionHandler;

    public function __construct(
        CaseQueueRepository $caseQueueRepository,
        ServiceCenterCaseRepository $caseRepository,
        AgentSessionRepository $agentSessionRepository,
        AgentSessionLogRepository $agentSessionLogRepository,
        ServiceCenterCaseService $serviceCenterCaseService,
        TransactionHandler $transactionHandler
    ) {
        $this->caseQueueRepository = $caseQueueRepository;
        $this->caseRepository = $caseRepository;
        $this->agentSessionRepository = $agentSessionRepository;
        $this->agentSessionLogRepository = $agentSessionLogRepository;
        $this->serviceCenterCaseService = $serviceCenterCaseService;
        $this->transactionHandler = $transactionHandler;
    }

    /**
     * 1) Assign telephony queued cases
     * 2) Assign paused cases
     * 3) Assign electronic queued cases
     */
    public function assignCasesToAgents(): void
    {
        // Interactive cases (ringing telephony cases) have highest prio
        $this->assignQueuedCasesToAgents(
            $this->caseQueueRepository->getAssignableInteractiveCasesByPrio(),
            $this->agentSessionRepository->findAgentSessionsAvailableForInteractiveCaseAssignment()
        );

        // Then let agents continue with their paused cases
        $this->tryAssignPausedCases();

        // Then all other non-interactive cases
        $this->assignQueuedCasesToAgents(
            $this->caseQueueRepository->getAssignablePassiveCasesByPrio(),
            $this->agentSessionRepository->findAgentSessionsAvailableForPassiveCaseAssignment()
        );
    }

    /**
     * @param CaseQueueEntry[] $caseQueueEntries
     * @param AgentSession[] $availableAgentSessions
     */
    private function assignQueuedCasesToAgents(iterable $caseQueueEntries, iterable $availableAgentSessions): void
    {
        $availableAgentSessionCollection = collect($availableAgentSessions);

        foreach ($caseQueueEntries as $caseQueueEntry) {
            if ($availableAgentSessionCollection->isEmpty()) {
                // We have no available agents remaining
                break;
            }

            $case = $caseQueueEntry->getCase();

            $assignedSession = $this->transactionHandler->doInLockedTransaction(
                $case,
                function () use ($case, $availableAgentSessionCollection) {
                    /** @var AgentSession $assignableSession */
                    $assignableSession = $availableAgentSessionCollection->first(
                        fn (AgentSession $agentSession) => $this->canAssignAgentToCase($case, $agentSession)
                    );

                    if (null === $assignableSession) {
                        // We cannot match an agent to this case
                        return null;
                    }

                    if ($assignableSession->hasActiveCase() && $case->isInteractiveCase()) {
                        // The assignable agent is working on an interruptible case, which will be paused in favor of
                        // the new interactive (= high priority) case
                        $this->serviceCenterCaseService->pauseCase($assignableSession->getActiveCase());
                        $assignableSession->refresh();
                    }

                    $this->serviceCenterCaseService->startCase($case, $assignableSession);

                    return $assignableSession;
                }
            );

            if ($assignedSession !== null) {
                // The case was assigned to an agent session, so remove that session from the list of available ones
                $availableAgentSessionCollection = $availableAgentSessionCollection->reject($assignedSession);
            }
        }
    }

    private function canAssignAgentToCase(ServiceCenterCase $case, AgentSession $agentSession): bool
    {
        // Check if workgroups match
        $caseWorkGroupId = $case->getCaseEntry()->getWorkGroup()->getId();
        $hasRequiredWorkgroup = some(
            $agentSession->getWorkGroups(),
            fn (WorkGroup $agentWorkGroup) => $agentWorkGroup->getId() === $caseWorkGroupId
        );

        if (!$hasRequiredWorkgroup) {
            // Agent workgroups do not match case workgroup
            return false;
        }

        // Don't automatically assign case to agent if the agent has already been assigned to the case before
        $caseHasNotBeenAssignedToAgentSessionBefore = !$this->agentSessionLogRepository->hasCaseBeenAssignedToAgentSession(
            $agentSession,
            $case
        );
        if (!$caseHasNotBeenAssignedToAgentSessionBefore) {
            // Has been assigned before to this agent session, so don't re-assign
            return false;
        }

        // All good, can assign
        return true;
    }

    private function tryAssignPausedCases(): void
    {
        $pausedCases = $this->caseRepository->findPausedCases();

        foreach ($pausedCases as $pausedCase) {
            $this->transactionHandler->doInLockedTransaction(
                $pausedCase,
                function () use ($pausedCase) {
                    /** @var User $assignedAgent */
                    $assignedAgent = $pausedCase->getCaseEntry()->getAssignedAgent();

                    $assignedAgentSession = $this->agentSessionRepository->getByAgent($assignedAgent);

                    if ($assignedAgentSession === null) {
                        return;
                    }

                    $agentSessionStatus = $assignedAgentSession->getAgentSessionLogEntry()->getStatus()->getValue();

                    $isAwaitingCase = AgentSessionStatus::AWAITING_CASE === $agentSessionStatus;

                    if ($isAwaitingCase) {
                        $this->serviceCenterCaseService->resumeCase($pausedCase);
                    }
                }
            );
        }
    }
}
