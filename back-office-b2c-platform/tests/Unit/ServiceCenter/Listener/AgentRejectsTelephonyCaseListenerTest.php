<?php

declare(strict_types=1);

namespace Tests\Unit\ServiceCenter\Listener;

use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\AgentSession\AgentSessionLogEntry;
use App\ServiceCenter\AgentSession\Repository\AgentSessionRepository;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\Listener\AgentRejectsTelephonyCaseListener;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\Telephony\ChannelReferences;
use App\Telephony\ChannelMeta;
use App\Telephony\Number\Repository\ServiceNumberLinkRepository;
use App\Telephony\Number\ServicenumberLink;
use App\Telephony\Provider\Netwerkplek\Netwerkplek;
use App\Telephony\Session\Event\AbstractEvent;
use App\Telephony\Session\Event\Channel\ChannelCreated;
use App\Telephony\Session\Event\Channel\ChannelStateSwitch;
use App\Telephony\Session\Event\EventFactory;
use App\Telephony\Session\Event\ServiceNumberMatch;
use App\Telephony\Session\Meta\TelephonySessionMetaUpdater;
use App\Telephony\Session\ProviderInfo;
use App\Telephony\Session\Repository\MemorySessionRepository;
use App\Telephony\Session\SessionStateMachineUpdater;
use App\Telephony\Session\State\Sub\ChannelState;
use App\Telephony\Session\StateMachineFactory;
use App\Telephony\Session\StateTransitionedEvent;
use App\Telephony\Session\TelephonySessionService;
use App\Telephony\Session\TelephonySessionStateMachine;
use App\Utils\Database\TransactionHandlerStub;
use App\Utils\Time\MockClock;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Propaganistas\LaravelPhone\PhoneNumber;
use Tests\Integration\Concerns\InteractsWithAgentSession;
use Tests\Integration\Concerns\InteractsWithTelephonySession;

class AgentRejectsTelephonyCaseListenerTest extends TestCase
{
    use InteractsWithTelephonySession, InteractsWithAgentSession;

    /** @var AgentRejectsTelephonyCaseListener */
    private $SUT;

    /** @var ServicenumberLink|MockObject */
    private $serviceNumberLink;

    /** @var SessionStateMachineUpdater */
    private $stateMachineUpdater;

    /** @var TelephonySessionStateMachine */
    private $stateMachine;

    /** @var AgentSessionService|MockObject */
    private $agentSessionServiceMock;

    /** @var ServiceCenterCaseService|MockObject */
    private $serviceCenterCaseServiceMock;

    /** @var MemorySessionRepository */
    private $telephonySessionRepository;

    private $agentSessionMock;

    public function setUp(): void
    {
        parent::setUp();

        $container = new Container();

        $this->serviceNumberLink = $this->createMock(ServicenumberLink::class);

        /** @var ServiceNumberLinkRepository|MockObject $serviceNumberLinkRepository */
        $serviceNumberLinkRepository = $this->createMock(ServiceNumberLinkRepository::class);
        $serviceNumberLinkRepository->method('find')
            ->willReturn($this->serviceNumberLink);

        $container->instance(ServiceNumberLinkRepository::class, $serviceNumberLinkRepository);

        $this->telephonySessionRepository = new MemorySessionRepository();
        $stateMachineFactory = new StateMachineFactory(
            new EventFactory($container),
            $this->telephonySessionRepository
        );
        $this->stateMachineUpdater = new SessionStateMachineUpdater(
            $this->telephonySessionRepository,
            $this->createMock(Dispatcher::class),
            $stateMachineFactory,
            $this->createMock(TelephonySessionMetaUpdater::class),
            new MockClock(),
            new TransactionHandlerStub()
        );
        $telephonySessionService = new TelephonySessionService(
            $this->telephonySessionRepository,
            $stateMachineFactory,
            $this->stateMachineUpdater
        );

        $this->stateMachine = $telephonySessionService->createNewSessionStateMachine();

        $this->serviceCenterCaseServiceMock = $this->createMock(ServiceCenterCaseService::class);
        $serviceCenterCaseRepositoryMock = $this->createMock(ServiceCenterCaseRepository::class);
        $this->agentSessionServiceMock = $this->createMock(AgentSessionService::class);
        $agentSessionRepositoryMock = $this->createMock(AgentSessionRepository::class);

        $caseMock = $this->createMock(ServiceCenterCase::class);
        $this->agentSessionMock = $this->createMock(AgentSession::class);

        $this->agentSessionMock
            ->method('getId')
            ->willReturn(1);

        $agentSessionRepositoryMock
            ->method('getById')
            ->willReturn($this->agentSessionMock);

        $serviceCenterCaseRepositoryMock
            ->method('tryGetBySourceTelephonySession')
            ->willReturn($caseMock);

        $caseMock
            ->method('getCurrentAgentSessionLogEntry')
            ->willReturn($this->createMock(AgentSessionLogEntry::class));

        $this->SUT = new AgentRejectsTelephonyCaseListener(
            $serviceCenterCaseRepositoryMock,
            $this->serviceCenterCaseServiceMock,
            $this->agentSessionServiceMock,
            new TransactionHandlerStub(),
            $agentSessionRepositoryMock,
            new Logger('test')
        );
    }

    public function testThatItHandlesAgentRejectTelephonyCase(): void
    {
        $inboundPhoneNumber = '+31612000001';

        $this->applyEvent(
            new ChannelCreated(
                'inbound-id',
                PhoneNumber::make($inboundPhoneNumber),
                PhoneNumber::make('+31880011223'),
                ChannelState::DIRECTION_INBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                []
            )
        );

        $this->applyEvent(
            new ServiceNumberMatch($this->serviceNumberLink)
        );

        $agentChannelId = 'agent-id';

        // Create agent channel
        $this->applyEvent(
            new ChannelCreated(
                $agentChannelId,
                PhoneNumber::make('+31610000001'),
                PhoneNumber::make('+31610000007'),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                [
                    ChannelMeta::REFERENCE => ChannelReferences::AGENT,
                    ChannelMeta::AGENT_SESSION_ID => $this->agentSessionMock->getId(),
                ]
            )
        );

        $this->applyEvent(new ChannelStateSwitch(
            $agentChannelId,
            ChannelState::STATE_TRYING
        ));

        $this->applyEvent(new ChannelStateSwitch(
            $agentChannelId,
            ChannelState::STATE_RINGING
        ));

        $this->applyEvent(new ChannelStateSwitch(
            $agentChannelId,
            ChannelState::STATE_FAULTED,
            ChannelState::FAULT_REASON_DECLINE
        ));

        $this->agentSessionServiceMock
            ->expects(self::atLeastOnce())
            ->method('detachTelephonySession');

        $this->serviceCenterCaseServiceMock
            ->expects(self::atLeastOnce())
            ->method('unassignAgentFromCaseForAgentSession');

        $this->SUT->onTelephonyStateChanged(
            new StateTransitionedEvent(
                $this->telephonySessionRepository->getById($this->stateMachine->getId()),
                $this->stateMachine
            )
        );
    }

    private function applyEvent(AbstractEvent $event): void
    {
        $this->stateMachine = $this->stateMachineUpdater->onEvent(
            $event,
            $this->stateMachine
        );
    }
}
