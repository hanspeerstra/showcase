<?php

declare(strict_types=1);

namespace Tests\Integration\ServiceCenter\Listener;

use App\InternalPhone\InternalPhone;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\CaseQueue\Repository\CaseQueueRepository;
use App\ServiceCenter\Listener\CaseTelephonySessionEndedListener;
use App\ServiceCenter\ServiceCenterCase\CaseType;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Telephony\Session\Model\TelephonySession;
use App\Telephony\Session\StateTransitionedEvent;
use App\Telephony\Session\TelephonySessionStateMachine;
use BaseTestSeeder;
use Carbon\CarbonImmutable;
use TestMygoSeeder;
use Tests\Integration\Concerns\InteractsWithTelephonySession;
use Tests\Integration\IntegrationTestCase;

class CaseTelephonySessionEndedListenerTest extends IntegrationTestCase
{
    use InteractsWithTelephonySession;

    /** @var CaseTelephonySessionEndedListener */
    private $SUT;

    /** @var TelephonySession */
    private $telephonySession;

    /** @var CaseQueueRepository */
    private $caseQueueRepository;

    /** @var WorkGroup */
    private $workGroup;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed([
            BaseTestSeeder::class,
            TestMygoSeeder::class,
        ]);

        $this->telephonySession = $this->givenTelephonySession(
            true,
            false
        );

        $this->SUT = $this->app->make(CaseTelephonySessionEndedListener::class);

        $this->caseQueueRepository = $this->app->make(CaseQueueRepository::class);

        $this->workGroup = factory(WorkGroup::class)->create();
    }

    public function testThatGivenEndedTelephonySessionDequeueCaseAndCloseCase(): void
    {
        $case = $this->createCase();
        $caseQueue = $this->caseQueueRepository->getByCase($case);

        self::assertNotNull($caseQueue);

        $this->telephonySession->ended_at = new CarbonImmutable();

        $this->telephonySession->save();

        $stateTransitionedEventMock = $this->createStateTransitionedEventMock();
        $this->SUT->onTelephonyStateChanged($stateTransitionedEventMock);

        self::assertNull($this->caseQueueRepository->getByCase($case));
    }

    public function testThatAgentSessionTelephonySessionIsRemoved(): void
    {
        $case = $this->createCase();

        $agentSession = $this->startAgentSession();

        $agentSession->refresh();
        self::assertTrue($agentSession->hasActiveTelephonySession());
        self::assertEquals($case->getId(), $agentSession->getAgentSessionLogEntry()->getServiceCenterCase()->getId());

        $this->telephonySession->ended_at = new CarbonImmutable();

        $this->telephonySession->save();

        $stateTransitionedEventMock = $this->createStateTransitionedEventMock();
        $this->SUT->onTelephonyStateChanged($stateTransitionedEventMock);

        $agentSession->refresh();
        $case->refresh();
        self::assertFalse($agentSession->hasActiveTelephonySession());
        self::assertNull($agentSession->getAgentSessionLogEntry()->getTelephonySession());
        self::assertNull($case->getCaseEntry()->getTelephonySession());
    }

    private function startAgentSession(): AgentSession
    {
        $agentSessionService = $this->app->make(AgentSessionService::class);

        $this->loginAsAdmin();

        return $agentSessionService->createAndStartSession(
            $this->loginAsAdmin(),
            factory(InternalPhone::class)->create(),
            true,
            1,
            ...[$this->workGroup]
        );
    }

    private function createStateTransitionedEventMock(): StateTransitionedEvent
    {
        $stateTransitionedEventMock = $this->createMock(StateTransitionedEvent::class);
        $telephonySessionStateMachineMock = $this->createMock(TelephonySessionStateMachine::class);

        $telephonySessionStateMachineMock
            ->method('getId')
            ->willReturn($this->telephonySession->getId());

        $stateTransitionedEventMock
            ->method('getTelephonySession')
            ->willReturn($this->telephonySession);

        $stateTransitionedEventMock
            ->method('hasTransitionedTo')
            ->willReturn(true);

        $stateTransitionedEventMock
            ->method('getSessionStateMachine')
            ->willReturn($telephonySessionStateMachineMock);

        $stateTransitionedEventMock
            ->method('getEventDate')
            ->willReturn(new CarbonImmutable());

        return $stateTransitionedEventMock;
    }

    private function createCase(): ServiceCenterCase
    {
        $case = ServiceCenterCase::makeInstance(
            factory(CaseType::class)->create(),
            $this->workGroup,
            null,
            null,
            $this->telephonySession,
            null
        );

        return $this->app->make(ServiceCenterCaseRepository::class)
            ->persist($case);
    }
}
