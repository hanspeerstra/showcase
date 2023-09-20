<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Http\Controller;

use App\Auth\Http\Resource\UserResource;
use App\Auth\User;
use App\Http\Controllers\Controller;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\AgentSession\Http\Request\AgentSessionAssignCasesAutomaticallyRequest;
use App\ServiceCenter\AgentSession\Http\Request\ManagerChangeAgentSessionAssignCasesAutomaticallyRequest;
use App\ServiceCenter\AgentSession\Http\Request\ManagerForceCloseAgentSessionRequest;
use App\ServiceCenter\AgentSession\Http\Request\StartAgentSessionRequest;
use App\ServiceCenter\AgentSession\Http\Resource\AgentSessionResource;
use App\ServiceCenter\AgentSession\Repository\AgentSessionRepository;
use App\ServiceCenter\AgentSession\Service\AgentSessionForceEndSessionService;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\CaseQueue\Http\Request\ShowCaseQueueRequest;
use App\ServiceCenter\CaseQueue\Http\Resource\CaseQueueEntryResource;
use App\ServiceCenter\CaseQueue\Repository\CaseQueueRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentSessionController extends Controller
{
    public function showCurrentAgentSession(Request $request): AgentSessionResource
    {
        /** @var User $user */
        $user = $request->user();

        return new AgentSessionResource(
            $user->getActiveAgentSession()
        );
    }

    public function show(AgentSession $agentSession): AgentSessionResource
    {
        return new AgentSessionResource($agentSession);
    }

    public function startAgentSession(
        StartAgentSessionRequest $request,
        AgentSessionService $agentSessionService
    ): AgentSessionResource {
        $agentSession = $agentSessionService->createAndStartSession(
            $request->getAgent(),
            $request->getInternalPhone(),
            $request->getAutomaticCall(),
            $request->getPriority(),
            ...$request->getWorkGroups()
        );

        return new AgentSessionResource($agentSession);
    }

    public function endAgentSession(
        Request $request,
        AgentSessionService $agentSessionService,
        AgentSessionRepository $agentSessionRepository
    ): void {
        /** @var AgentSession $agentSession */
        $agentSession = $agentSessionRepository->getByAgent($request->user());

        $agentSessionService->endSession($agentSession);
    }

    public function managerForceEndAgentSession(
        ManagerForceCloseAgentSessionRequest $request,
        AgentSessionForceEndSessionService $agentSessionForceEndSessionService
    ): void {
        $agentSessionForceEndSessionService
            ->forceEndSession(
                $request->getTargetAgentSession()
            );
    }

    public function agentSessionAutomaticQueue(
        AgentSessionAssignCasesAutomaticallyRequest $request,
        AgentSessionService $agentSessionService,
        AgentSessionRepository $agentSessionRepository
    ): void {
        /** @var AgentSession $agentSession */
        $agentSession = $agentSessionRepository->getByAgent($request->getAgent());

        $agentSessionService->assignCasesAutomatically(
            $agentSession,
            $request->getAssignCasesAutomatically(),
            $request->getPriority()
        );
    }

    public function managerChangeAgentSessionAutomaticAutomatically(
        ManagerChangeAgentSessionAssignCasesAutomaticallyRequest $request,
        AgentSessionService $agentSessionService
    ): void {
        $agentSessionService->assignCasesAutomatically(
            $request->getTargetAgentSession(),
            $request->getAssignCasesAutomatically(),
            $request->getPriority()
        );
    }

    public function assignCase(
        Request $request,
        ServiceCenterCase $case,
        ServiceCenterCaseService $serviceCenterCaseService
    ): void {
        /** @var User $user */
        $user = $request->user();

        $serviceCenterCaseService->startCase(
            $case,
            $user->getActiveAgentSession()
        );
    }

    public function unassignCase(
        Request $request,
        ServiceCenterCase $case,
        ServiceCenterCaseService $serviceCenterCaseService
    ): void {
        /** @var User $user */
        $user = $request->user();

        $serviceCenterCaseService->unassignAgentFromCaseForAgentSession(
            $case,
            $user->getActiveAgentSession()
        );
    }

    public function rejectCase(
        Request $request,
        ServiceCenterCase $case,
        ServiceCenterCaseService $serviceCenterCaseService
    ): void {
        /** @var User $user */
        $user = $request->user();

        // TODO handle this through Networkplek, so in this case there should be a call to Networkplek to reject the call.
        $serviceCenterCaseService->agentRejectCase($case, $user->getActiveAgentSession());
    }

    public function pauseCase(
        Request $request,
        ServiceCenterCase $case,
        ServiceCenterCaseService $serviceCenterCaseService
    ): void {
        /** @var User $user */
        $user = $request->user();

        $serviceCenterCaseService->pauseCaseFromAgentSession(
            $case,
            $user->getActiveAgentSession()
        );
    }

    public function activeAgentSessions(AgentSessionRepository $agentSessionRepository): JsonResource
    {
        return AgentSessionResource::collection(
            $agentSessionRepository->findActiveAgentSessions()
        );
    }

    public function uniqueAgents(AgentSessionService $agentSessionService): JsonResource
    {
        return UserResource::collection(
            $agentSessionService->getUniqueAgents()
        );
    }

    public function getCaseQueue(ShowCaseQueueRequest $request, CaseQueueRepository $caseQueueRepository): JsonResource
    {
        return CaseQueueEntryResource::collection(
            $caseQueueRepository->getAllByWorkGroups(
                ...$request->getAgentSession()->getWorkGroups()
            )
        );
    }
}
