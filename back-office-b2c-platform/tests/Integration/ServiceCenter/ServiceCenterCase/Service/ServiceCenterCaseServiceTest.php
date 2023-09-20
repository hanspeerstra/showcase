<?php

declare(strict_types=1);

namespace Tests\Integration\ServiceCenter\ServiceCenterCase\Service;

use App\Auth\User;
use App\InternalPhone\InternalPhone;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\AgentSession\AgentSessionLogEntry;
use App\ServiceCenter\AgentSession\AgentSessionStatus;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\ServiceCenterCase\CaseType;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseEntry;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseGarbageReason;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Telephony\Session\Model\TelephonySession;
use BaseTestSeeder;
use Carbon\CarbonImmutable;
use TestMygoSeeder;
use Tests\Integration\Concerns\InteractsWithTelephonySession;
use Tests\Integration\IntegrationTestCase;

class ServiceCenterCaseServiceTest extends IntegrationTestCase
{
    use InteractsWithTelephonySession;

    private ServiceCenterCaseService $SUT;
    private User $user;
    private AgentSession $agentSession;
    private TelephonySession $telephonySession;
    private ServiceCenterCase $case;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed([
            BaseTestSeeder::class,
            TestMygoSeeder::class,
        ]);
        $this->SUT = $this->app->make(ServiceCenterCaseService::class);
        /** @var AgentSessionService $agentSessionService */
        $agentSessionService = $this->app->make(AgentSessionService::class);
        $serviceCenterCaseRepository = $this->app->make(ServiceCenterCaseRepository::class);

        $workGroup = factory(WorkGroup::class)->create();
        $this->user = factory(User::class)->create();
        $caseType = factory(CaseType::class)->create();

        $this->telephonySession = $this->givenTelephonySession(true, false);

        $this->agentSession = $agentSessionService->createAndStartSession(
            $this->user,
            factory(InternalPhone::class)->create(),
            true,
            1,
            ...[$workGroup]
        );

        $this->case = ServiceCenterCase::makeInstance(
            $caseType,
            $workGroup,
            null,
            null,
            $this->telephonySession,
            null
        );

        $serviceCenterCaseRepository->persist($this->case);
    }

    public function testThatItStartsACaseCorrectly(): void
    {
        $this->case->refresh();

        self::assertEquals(
            AgentSessionStatus::HANDLE_CASE,
            $this->case->getCurrentAgentSessionLogEntry()->getStatus()->getValue()
        );

        $agentSessionLogEntryCount = AgentSessionLogEntry::withTrashed()
            ->where('agent_session_id', '=', $this->agentSession->getId())
            ->count();

        $caseEntryCount = ServiceCenterCaseEntry::withTrashed()
            ->where('case_id', '=', $this->case->getId())
            ->count();

        self::assertSame(2, $agentSessionLogEntryCount);
        self::assertSame(2, $caseEntryCount);

        self::assertNotNull($this->case->getStartedAt());

        self::assertSame($this->case->getCaseEntry()->getAssignedAgent()->getId(), $this->user->getId());
    }

    public function testThatItPausesACase(): void
    {
        $this->closeCaseTelephonySession();

        $this->case = $this->SUT->pauseCase($this->case);

        $this->assertDatabaseMissing('sc_agent_session_log', [
            'agent_session_id' => $this->agentSession->getId(),
            'case_id' => $this->case->getId(),
            'telephony_session_id' => $this->telephonySession->getId(),
            'deleted_at' => null,
        ]);

        self::assertNull($this->case->getCurrentAgentSessionLogEntry());

        self::assertEquals($this->agentSession->getUser()->getId(), $this->case->getCaseEntry()->getAssignedAgent()->getId());
    }

    public function testThatItResumesACase(): void
    {
        $this->closeCaseTelephonySession();

        $this->case = $this->SUT->pauseCase($this->case);

        $this->SUT->resumeCase($this->case);

        $this->assertDatabaseHas('sc_agent_session_log', [
            'agent_session_id' => $this->agentSession->getId(),
            'case_id' => $this->case->getId(),
            'status' => AgentSessionStatus::HANDLE_CASE,
            'telephony_session_id' => null,
            'deleted_at' => null,
        ]);

        $agentSessionLogEntryCount = AgentSessionLogEntry::withTrashed()
            ->where('agent_session_id', '=', $this->agentSession->getId())
            ->count();

        self::assertSame(4, $agentSessionLogEntryCount);
    }

    public function testThatItHandlesCaseResult(): void
    {
        $this->case = $this->SUT->setCaseResult($this->case, null, null, $this->getGarbageReason());

        $this->assertDatabaseHas('sc_cases', [
            'id' => $this->case->getId(),
            'garbage_reason_id' => $this->case->getGarbageReason()->getId(),
        ]);

        $this->expectExceptionMessageMatches('/Case result can only be set once/');

        $this->SUT->setCaseResult($this->case, null, null, $this->getGarbageReason());
    }

    public function testItCannotSetCaseResultWithoutResult(): void
    {
        $this->expectExceptionMessageMatches('/Result must be set, no result given/');

        $this->SUT->setCaseResult($this->case, null, null, null);
    }

    public function testThatItCanChangeCaseType(): void
    {
        $this->case->refresh();

        /** @var CaseType $newCaseType */
        $newCaseType = factory(CaseType::class)->create();
        /** @var WorkGroup $newWorkGroup */
        $newWorkGroup = factory(WorkGroup::class)->create();

        $this->SUT->changeCaseType(
            $this->case,
            $newCaseType,
            $newWorkGroup,
            true
        );

        $this->assertDatabaseHas('sc_case_entries', [
            'case_id' => $this->case->getId(),
            'case_type_id' => $newCaseType->getId(),
            'work_group_id' => $newWorkGroup->getId(),
            'assigned_agent_id' => $this->case->getCaseEntry()->getAssignedAgent()->getId(),
            'deleted_at' => null,
        ]);

        $this->SUT->changeCaseType(
            $this->case,
            $newCaseType,
            $newWorkGroup,
            false
        );

        $this->assertDatabaseHas('sc_case_entries', [
            'case_id' => $this->case->getId(),
            'case_type_id' => $newCaseType->getId(),
            'work_group_id' => $newWorkGroup->getId(),
            'assigned_agent_id' => null,
            'deleted_at' => null,
        ]);
    }

    public function testThatItUnassignAgentFromCase(): void
    {
        $this->closeCaseTelephonySession();

        $this->SUT->unassignAgentFromCase($this->case);

        $this->assertDatabaseHas('sc_case_entries', [
            'case_id' => $this->case->getId(),
            'assigned_agent_id' => null,
            'deleted_at' => null,
        ]);

        $this->agentSession->refresh();

        self::assertNotEquals(
            AgentSessionStatus::HANDLE_CASE,
            $this->agentSession->getAgentSessionLogEntry()->getStatus()->getValue()
        );
    }

    public function testThatItCloseACase(): void
    {
        $this->closeCaseTelephonySession();

        $this->case = $this->SUT->setCaseResult($this->case, null, null, $this->getGarbageReason());

        $this->case = $this->SUT->closeCase($this->case);

        self::assertNotNull($this->case->getClosedAt());

        $this->assertDatabaseHas('sc_cases', [
            'id' => $this->case->getId(),
            'closed_at' => $this->case->getClosedAt(),
        ]);
    }

    private function closeCaseTelephonySession(): void
    {
        $this->telephonySession->ended_at = new CarbonImmutable();
        $this->telephonySession->save();
        $this->case->refresh();

        $this->SUT->assertThatCaseHasAnActiveAgentSession($this->case);

        $agentSession = $this->case->getCurrentAgentSessionLogEntry()->getAgentSession();

        $this->SUT->assertThatAgentSessionDoesNotHaveAnActiveTelephonySession($agentSession);

        AgentSessionLogEntry::new(
            $agentSession,
            $this->case->getCurrentAgentSessionLogEntry()->getStatus(),
            $this->case,
            null
        );
    }

    private function getGarbageReason(): ServiceCenterCaseGarbageReason
    {
        return ServiceCenterCaseGarbageReason::query()
            ->belongsToLabel(ServiceCenterCaseGarbageReason::LABEL_CLOSED_BY_SYSTEM_USER)
            ->firstOrFail();
    }
}
