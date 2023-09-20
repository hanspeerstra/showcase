<?php

declare(strict_types=1);

namespace Tests\Unit\ServiceCenter\Telephony;

use App\ServiceCenter\Telephony\ChannelReferences;
use App\ServiceCenter\Telephony\DerivedTelephonyState;
use App\ServiceCenter\Telephony\Factory\DerivedTelephonyStateFactory;
use App\Telephony\ChannelMeta;
use App\Telephony\Number\Repository\ServiceNumberLinkRepository;
use App\Telephony\Number\ServicenumberLink;
use App\Telephony\Provider\Netwerkplek\Netwerkplek;
use App\Telephony\Session\Event\AbstractEvent;
use App\Telephony\Session\Event\Channel\AudioConnectionChange;
use App\Telephony\Session\Event\Channel\ChannelCreated;
use App\Telephony\Session\Event\Channel\ChannelStateSwitch;
use App\Telephony\Session\Event\EventFactory;
use App\Telephony\Session\Event\ServiceNumberMatch;
use App\Telephony\Session\Meta\TelephonySessionMetaUpdater;
use App\Telephony\Session\Model\TelephonySession;
use App\Telephony\Session\ProviderInfo;
use App\Telephony\Session\Repository\MemorySessionRepository;
use App\Telephony\Session\SessionStateMachineUpdater;
use App\Telephony\Session\State\Sub\ChannelState;
use App\Telephony\Session\StateMachineFactory;
use App\Telephony\Session\TelephonySessionService;
use App\Telephony\Session\TelephonySessionStateMachine;
use App\Utils\Database\TransactionHandlerStub;
use App\Utils\Time\MockClock;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Propaganistas\LaravelPhone\PhoneNumber;

class DerivedTelephonyStateFactoryTest extends TestCase
{
    /** @var DerivedTelephonyStateFactory */
    private $SUT;

    /** @var MemorySessionRepository */
    private $telephonySessionRepository;

    /** @var SessionStateMachineUpdater */
    private $stateMachineUpdater;

    /** @var TelephonySessionStateMachine */
    private $stateMachine;

    /** @var ServicenumberLink */
    private $serviceNumberLink;

    protected function setUp(): void
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

        $this->SUT = new DerivedTelephonyStateFactory($stateMachineFactory);

        $this->stateMachine = $telephonySessionService->createNewSessionStateMachine();
    }

    public function testItDerivesCorrectlyInboundAndOutboundCall(): void
    {
        $inboundPhoneNumber = '+31612000001';
        $outboundPhoneNumber = '+31612345678';

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

        // Create agent channel
        $this->applyEvent(
            new ChannelCreated(
                'agent-id',
                PhoneNumber::make('+31610000001'),
                PhoneNumber::make('+31610000007'),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                [ChannelMeta::REFERENCE => ChannelReferences::AGENT]
            )
        );

        $this->applyEvent(
            new ChannelCreated(
                'outbound-id',
                PhoneNumber::make('+31610000007'),
                PhoneNumber::make($outboundPhoneNumber),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                [
                    ChannelMeta::REFERENCE => ChannelReferences::COMPANY,
                    ChannelMeta::COMPANY_ID => 123,
                ]
            )
        );

        $derivedState = $this->SUT->createFromTelephonySession($this->getTelephonySession());

        self::assertCount(2, $derivedState->getChannels());
        self::assertNotNull($derivedState->getChannelByReference(ChannelReferences::COMPANY));
        self::assertTrue($derivedState->hasChannel('inbound-id'));
        self::assertTrue($derivedState->hasChannel('outbound-id'));
        self::assertFalse($derivedState->hasChannel('agent-id'));
        self::assertFalse($derivedState->hasChannelByReference(ChannelReferences::AGENT));

        $inboundChannel = $derivedState->getChannel('inbound-id');
        $outboundChannel = $derivedState->getChannel('outbound-id');

        self::assertSame('inbound-id', $inboundChannel->getChannelId());
        self::assertNull($inboundChannel->getReference());
        self::assertSame(0, $inboundChannel->getLineNumber());
        self::assertSame($inboundPhoneNumber, $inboundChannel->getRemotePhoneNumber()->formatE164());

        self::assertSame('outbound-id', $outboundChannel->getChannelId());
        self::assertSame(ChannelReferences::COMPANY, $outboundChannel->getReference());
        self::assertSame(1, $outboundChannel->getLineNumber());
        self::assertSame($outboundPhoneNumber, $outboundChannel->getRemotePhoneNumber()->formatE164());
        self::assertSame(123, $outboundChannel->getCompanyId());
    }

    public function testItDerivesCorrectlyNoTelephonySession(): void
    {
        $derivedState = $this->SUT->createFromTelephonySession(null);

        self::assertEquals(DerivedTelephonyState::inactive(), $derivedState);
    }

    public function testItDerivesLineNumbersCorrectly(): void
    {
        $inboundPhoneNumber = '+31612000001';
        $outboundPhoneNumber = '+31612345678';

        // line 0
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

        // Create agent channel
        $this->applyEvent(
            new ChannelCreated(
                'agent-id',
                PhoneNumber::make('+31610000001'),
                PhoneNumber::make('+31610000007'),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                [ChannelMeta::REFERENCE => ChannelReferences::AGENT]
            )
        );

        // line 1
        $this->applyEvent(
            new ChannelCreated(
                'outbound-id-1',
                PhoneNumber::make('+31610000007'),
                PhoneNumber::make($outboundPhoneNumber),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                []
            )
        );

        // line 2
        $this->applyEvent(
            new ChannelCreated(
                'outbound-id-2',
                PhoneNumber::make('+31610000007'),
                PhoneNumber::make($outboundPhoneNumber),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                []
            )
        );

        // hangup line 1
        $this->applyEvent(
            new ChannelStateSwitch(
                'outbound-id-1',
                ChannelState::STATE_HANGUP,
                null,
                ChannelState::HANGUP_INITIATOR_REMOTE
            )
        );

        $derivedState = $this->SUT->createFromTelephonySession($this->getTelephonySession());

        self::assertCount(2, $derivedState->getChannels());
        self::assertTrue($derivedState->hasChannel('inbound-id'));
        self::assertTrue($derivedState->hasChannel('outbound-id-2'));
        self::assertSame(0, $derivedState->getChannel('inbound-id')->getLineNumber());
        self::assertSame(2, $derivedState->getChannel('outbound-id-2')->getLineNumber());

        // should replace line 1
        $this->applyEvent(
            new ChannelCreated(
                'outbound-id-3',
                PhoneNumber::make('+31610000007'),
                PhoneNumber::make($outboundPhoneNumber),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                []
            )
        );

        $derivedState = $this->SUT->createFromTelephonySession($this->getTelephonySession());

        self::assertCount(3, $derivedState->getChannels());
        self::assertTrue($derivedState->hasChannel('inbound-id'));
        self::assertTrue($derivedState->hasChannel('outbound-id-2'));
        self::assertTrue($derivedState->hasChannel('outbound-id-3'));
        self::assertSame(0, $derivedState->getChannel('inbound-id')->getLineNumber());
        self::assertSame(1, $derivedState->getChannel('outbound-id-3')->getLineNumber());
        self::assertSame(2, $derivedState->getChannel('outbound-id-2')->getLineNumber());
    }

    public function testIncomingCallWithAgent(): void
    {
        // incoming call
        $this->applyEvent(
            new ChannelCreated(
                'inbound-id',
                PhoneNumber::make('+31612000001'),
                PhoneNumber::make('+31880011223'),
                ChannelState::DIRECTION_INBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                []
            )
        );

        $this->applyEvent(
            new ServiceNumberMatch($this->serviceNumberLink)
        );

        // Create agent channel
        $this->applyEvent(
            new ChannelCreated(
                'agent-id',
                PhoneNumber::make('+31610000001'),
                PhoneNumber::make('+31610000007'),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                [ChannelMeta::REFERENCE => ChannelReferences::AGENT]
            )
        );

        // connect inbound channel to agent
        $this->applyEvent(
            new AudioConnectionChange(
                'inbound-id',
                'agent-id',
                true
            )
        );
        // connect agent to inbound channel
        $this->applyEvent(
            new AudioConnectionChange(
                'agent-id',
                'inbound-id',
                true
            )
        );

        $derivedState = $this->SUT->createFromTelephonySession($this->getTelephonySession());

        self::assertTrue($derivedState->agentParticipatesInCall());
        self::assertTrue($derivedState->getChannel('inbound-id')->isAudioConnectedToAgent());
        self::assertFalse($derivedState->isOnHold());
        self::assertFalse($derivedState->isForwarded());
    }

    public function testSwitchedToOtherLine(): void
    {
        // incoming call
        $this->applyEvent(
            new ChannelCreated(
                'inbound-id',
                PhoneNumber::make('+31612000001'),
                PhoneNumber::make('+31880011223'),
                ChannelState::DIRECTION_INBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                []
            )
        );

        $this->applyEvent(
            new ServiceNumberMatch($this->serviceNumberLink)
        );

        // Create agent channel
        $this->applyEvent(
            new ChannelCreated(
                'agent-id',
                PhoneNumber::make('+31610000001'),
                PhoneNumber::make('+31610000007'),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                [ChannelMeta::REFERENCE => ChannelReferences::AGENT]
            )
        );

        // Create company channel
        $this->applyEvent(
            new ChannelCreated(
                'company-id',
                PhoneNumber::make('+31610000007'),
                PhoneNumber::make('+31610000002'),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                [ChannelMeta::REFERENCE => ChannelReferences::COMPANY]
            )
        );

        // connect company channel to agent
        $this->applyEvent(
            new AudioConnectionChange(
                'company-id',
                'agent-id',
                true
            )
        );
        // connect agent to company channel
        $this->applyEvent(
            new AudioConnectionChange(
                'agent-id',
                'company-id',
                true
            )
        );

        $derivedState = $this->SUT->createFromTelephonySession($this->getTelephonySession());

        self::assertTrue($derivedState->agentParticipatesInCall());
        self::assertFalse($derivedState->getChannel('inbound-id')->isAudioConnectedToAgent());
        self::assertTrue($derivedState->getChannel('company-id')->isAudioConnectedToAgent());
        self::assertFalse($derivedState->isOnHold());
        self::assertFalse($derivedState->isForwarded());
    }

    public function testOnHoldCall(): void
    {
        // incoming call
        $this->applyEvent(
            new ChannelCreated(
                'inbound-id',
                PhoneNumber::make('+31612000001'),
                PhoneNumber::make('+31880011223'),
                ChannelState::DIRECTION_INBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                []
            )
        );

        $this->applyEvent(
            new ServiceNumberMatch($this->serviceNumberLink)
        );

        // Create agent channel
        $this->applyEvent(
            new ChannelCreated(
                'agent-id',
                PhoneNumber::make('+31610000001'),
                PhoneNumber::make('+31610000007'),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                [ChannelMeta::REFERENCE => ChannelReferences::AGENT]
            )
        );

        // connect inbound channel to agent
        $this->applyEvent(
            new AudioConnectionChange(
                'inbound-id',
                'agent-id',
                true
            )
        );
        // connect agent to inbound channel
        $this->applyEvent(
            new AudioConnectionChange(
                'agent-id',
                'inbound-id',
                true
            )
        );

        // disconnect inbound channel from agent
        $this->applyEvent(
            new AudioConnectionChange(
                'inbound-id',
                'agent-id',
                false
            )
        );
        // disconnect agent from inbound channel
        $this->applyEvent(
            new AudioConnectionChange(
                'agent-id',
                'inbound-id',
                false
            )
        );

        $derivedState = $this->SUT->createFromTelephonySession($this->getTelephonySession());

        self::assertTrue($derivedState->agentParticipatesInCall());
        self::assertFalse($derivedState->getChannel('inbound-id')->isAudioConnectedToAgent());
        self::assertTrue($derivedState->isOnHold());
        self::assertFalse($derivedState->isForwarded());
    }

    public function testForwardedCall(): void
    {
        // incoming call
        $this->applyEvent(
            new ChannelCreated(
                'inbound-id',
                PhoneNumber::make('+31612000001'),
                PhoneNumber::make('+31880011223'),
                ChannelState::DIRECTION_INBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                []
            )
        );

        $this->applyEvent(
            new ServiceNumberMatch($this->serviceNumberLink)
        );

        // Create agent channel
        $this->applyEvent(
            new ChannelCreated(
                'agent-id',
                PhoneNumber::make('+31610000001'),
                PhoneNumber::make('+31610000007'),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                [ChannelMeta::REFERENCE => ChannelReferences::AGENT]
            )
        );

        // Create company channel
        $this->applyEvent(
            new ChannelCreated(
                'company-id',
                PhoneNumber::make('+31610000007'),
                PhoneNumber::make('+31610000002'),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                [ChannelMeta::REFERENCE => ChannelReferences::COMPANY]
            )
        );

        // connect inbound channel to company channel
        $this->applyEvent(
            new AudioConnectionChange(
                'inbound-id',
                'company-id',
                true
            )
        );
        // connect company channel to inbound channel
        $this->applyEvent(
            new AudioConnectionChange(
                'company-id',
                'inbound-id',
                true
            )
        );

        $derivedState = $this->SUT->createFromTelephonySession($this->getTelephonySession());

        self::assertFalse($derivedState->agentParticipatesInCall());
        self::assertFalse($derivedState->getChannel('inbound-id')->isAudioConnectedToAgent());
        self::assertFalse($derivedState->getChannel('company-id')->isAudioConnectedToAgent());
        self::assertFalse($derivedState->isOnHold());
        self::assertTrue($derivedState->isForwarded());
    }

    public function testHasAgentAnsweredState(): void
    {
        // incoming call
        $this->applyEvent(
            new ChannelCreated(
                'inbound-id',
                PhoneNumber::make('+31612000001'),
                PhoneNumber::make('+31880011223'),
                ChannelState::DIRECTION_INBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                [ChannelMeta::REFERENCE => ChannelReferences::CALLER]
            )
        );

        $this->applyEvent(
            new ServiceNumberMatch($this->serviceNumberLink)
        );

        // Create agent channel
        $this->applyEvent(
            new ChannelCreated(
                'agent-id',
                PhoneNumber::make('+31610000001'),
                PhoneNumber::make('+31610000007'),
                ChannelState::DIRECTION_OUTBOUND,
                new ProviderInfo(Netwerkplek::LABEL),
                [ChannelMeta::REFERENCE => ChannelReferences::AGENT]
            )
        );

        $this->applyEvent(
            new ChannelStateSwitch(
                'agent-id',
                ChannelState::STATE_TRYING
            )
        );

        $this->applyEvent(
            new ChannelStateSwitch(
                'agent-id',
                ChannelState::STATE_RINGING
            )
        );

        $derivedState = $this->SUT->createFromTelephonySession($this->getTelephonySession());
        self::assertFalse($derivedState->hasAgentAnswered());

        $this->applyEvent(
            new ChannelStateSwitch(
                'agent-id',
                ChannelState::STATE_ANSWERED
            )
        );

        $derivedState = $this->SUT->createFromTelephonySession($this->getTelephonySession());
        self::assertTrue($derivedState->hasAgentAnswered());
    }

    private function getTelephonySession(): TelephonySession
    {
        return $this->telephonySessionRepository->getById($this->stateMachine->getId());
    }

    private function applyEvent(AbstractEvent $event): void
    {
        $this->stateMachine = $this->stateMachineUpdater->onEvent(
            $event,
            $this->stateMachine
        );
    }
}
