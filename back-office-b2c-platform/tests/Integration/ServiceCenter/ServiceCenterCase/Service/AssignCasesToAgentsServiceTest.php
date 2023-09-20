<?php

declare(strict_types=1);

namespace Tests\Integration\ServiceCenter\ServiceCenterCase\Service;

use App\Auth\User;
use App\InternalPhone\InternalPhone;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\AgentSession\AgentSessionStatus;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\ServiceCenterCase\CaseType;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\Service\AssignCasesToAgentsService;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\WorkGroup\WorkGroup;
use TestMygoSeeder;
use Tests\Integration\Concerns\InteractsWithCase;
use Tests\Integration\IntegrationTestCase;

class AssignCasesToAgentsServiceTest extends IntegrationTestCase
{
    use InteractsWithCase;

    /** @var AssignCasesToAgentsService */
    private $SUT;

    /** @var CaseType */
    private $caseType;

    /** @var WorkGroup */
    private $workGroup;

    /** @var AgentSession */
    private $awaitingCaseAgentSession;

    /** @var ServiceCenterCaseRepository */
    private $serviceCenterCaseRepository;

    /** @var ServiceCenterCaseService */
    private $serviceCenterCaseService;

    public function setUp(): void
    {
        parent::setUp();

        $this->SUT = $this->app->make(AssignCasesToAgentsService::class);

        $agentSessionService = $this->app->make(AgentSessionService::class);
        $this->serviceCenterCaseRepository = $this->app->make(ServiceCenterCaseRepository::class);
        $this->serviceCenterCaseService = $this->app->make(ServiceCenterCaseService::class);

        $this->seed([
            TestMygoSeeder::class,
        ]);

        $internalPhone = factory(InternalPhone::class)->create();
        $this->caseType = factory(CaseType::class)->create();
        $this->workGroup = factory(WorkGroup::class)->create();

        $this->awaitingCaseAgentSession = $agentSessionService->createAndStartSession(
            factory(User::class)->create(),
            $internalPhone,
            true,
            1,
            $this->workGroup
        );
    }

    public function testThatItAutomaticallyAssignsAnAgentToACase(): void
    {
        $case = $this->getCase();

        self::assertEquals($case->getId(), $this->awaitingCaseAgentSession->getAgentSessionLogEntry()->getServiceCenterCase()->getId());
    }

    public function testThatItAutomaticallyReassignLowPriorityCase(): void
    {
        $lowPriorityCase = $this->getCase();

        $this->awaitingCaseAgentSession->refresh();

        self::assertEquals($lowPriorityCase->getId(), $this->awaitingCaseAgentSession->getAgentSessionLogEntry()->getServiceCenterCase()->getId());

        $telephonyCase = $this->getCase(true);

        $this->awaitingCaseAgentSession->refresh();

        self::assertEquals($telephonyCase->getId(), $this->awaitingCaseAgentSession->getAgentSessionLogEntry()->getServiceCenterCase()->getId());
    }

    public function testThatItResumeACaseAutomatically(): void
    {
        $case = $this->getCase();

        self::assertEquals($case->getId(), $this->awaitingCaseAgentSession->getAgentSessionLogEntry()->getServiceCenterCase()->getId());

        $case = $this->serviceCenterCaseService->pauseCase($case);

        $this->awaitingCaseAgentSession->refresh();

        // Case is paused and thus agent is unassigned from the case
        self::assertNull($case->getCurrentAgentSessionLogEntry());

        // Case paused  then Agent is set back to his initial/starting state
        self::assertSame(
            AgentSessionStatus::AWAITING_CASE,
            $this->awaitingCaseAgentSession->getAgentSessionLogEntry()->getStatus()->getValue()
        );

        // resume paused case(s)
        $this->SUT->assignCasesToAgents();

        $this->awaitingCaseAgentSession->refresh();

        $case = $this->serviceCenterCaseRepository->refresh($case);

        // Agent is re-assigned to paused case
        self::assertEquals(
            $case->getCurrentAgentSessionLogEntry(),
            $this->awaitingCaseAgentSession->getAgentSessionLogEntry()
        );
    }

    private function getCase(bool $isTelephonyCase = false): ServiceCenterCase
    {
        return $this->givenCase(
            $isTelephonyCase,
            $this->caseType,
            $this->workGroup
        );
    }
}
