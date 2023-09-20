<?php

declare(strict_types=1);

namespace Tests\Integration\ServiceCenter\Telephony\Http\Controller;

use App\Models\Office\Site;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\AgentSession\AgentSessionStatus;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\Telephony\ChannelReferences;
use App\Telephony\ChannelMeta;
use App\Telephony\Number\Repository\ServiceNumberLinkRepository;
use App\Telephony\Number\Repository\ServiceNumberRepository;
use App\Telephony\Number\Servicenumber;
use App\Telephony\Number\ServicenumberLink;
use App\Telephony\Provider\Netwerkplek\Netwerkplek;
use App\Telephony\Session\Event\Channel\AudioConnectionChange;
use App\Telephony\Session\Event\Channel\ChannelCreated;
use App\Telephony\Session\Meta\TelephonySessionMetaUpdater;
use App\Telephony\Session\Model\TelephonySession;
use App\Telephony\Session\ProviderInfo;
use App\Telephony\Session\Repository\SessionRepositoryInterface;
use App\Telephony\Session\SessionStateMachineUpdater;
use App\Telephony\Session\State\Concerns\CanSendCommands;
use App\Telephony\Session\State\Sub\ChannelState;
use App\Telephony\Session\State\Sub\CommandState;
use App\Telephony\Session\StateMachineFactory;
use App\Telephony\Session\TelephonySessionService;
use App\Telephony\Tracking\Models\CallTrackingSegment;
use App\Utils\Database\Contract\TransactionHandler;
use App\Utils\Time\MockClock;
use Illuminate\Contracts\Events\Dispatcher;
use Propaganistas\LaravelPhone\PhoneNumber;
use Tests\Integration\Concerns\InteractsWithAgentSession;
use Tests\Integration\Concerns\InteractsWithTelephonySession;
use Tests\Integration\IntegrationTestCase;

class ServiceCenterTelephonyControllerTest extends IntegrationTestCase
{
    use InteractsWithAgentSession;
    use InteractsWithTelephonySession;

    public function testItReturnsResponseWhenNotHavingTelephonySession(): void
    {
        $this->loginAsAdmin();

        $this->givenUserHavingAgentSession($this->getLoggedInUser());

        $response = $this->getJson(route('admin.sc.api.agent-session.current.telephony.state'));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'derivedTelephonyState' => [
                    'channels' => [],
                    'numberOfChannels' => 0,
                    'agentParticipatesInCall' => false,
                    'onHold' => false,
                    'forwarded' => false,
                ],
                'telephonySession' => null,
                'telephonySessionState' => null,
            ],
        ]);
    }

    public function testItReturnsResponseWhenHavingTelephonySessionWithChannel(): void
    {
        $this->loginAsAdmin();

        $agentSession = $this->givenUserHavingAgentSession($this->getLoggedInUser());
        $telephonySession = $this->givenTelephonySessionWithChannels();
        $this->givenAgentSessionHavingTelephonySession($agentSession, $telephonySession);

        $response = $this->getJson(route('admin.sc.api.agent-session.current.telephony.state'));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'derivedTelephonyState' => [
                    'channels' => [
                        [
                            'channelId' => 'test',
                            'reference' => 'caller',
                            'state' => 'connecting',
                            'lineNumber' => 1,
                            'phoneNumber' => '+31612345678',
                            'audioConnectedToAgent' => false,
                        ],
                    ],
                    'numberOfChannels' => 1,
                    'agentParticipatesInCall' => true,
                    'onHold' => true,
                    'forwarded' => false,
                ],
                'telephonySession' => [
                    'id' => $telephonySession->getId(),
                ],
                'telephonySessionState' => [
                    'state' => 'InboundCallMatchedState',
                    'isFinal' => false,
                    'provider' => Netwerkplek::LABEL,
                    'channels' => [
                        [
                            'channelId' => 'test',
                            'reference' => 'caller',
                            'source' => '+31612345678',
                            'destination' => '+31881111111',
                            'direction' => 'inbound',
                            'state' => 'created',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testItCanSwitchAudioToChannel(): void
    {
        $this->loginAsAdmin();

        $agentSession = $this->givenUserHavingAgentSession($this->getLoggedInUser());
        $telephonySession = $this->givenTelephonySession(true, true);
        $this->givenAgentSessionHavingTelephonySession($agentSession, $telephonySession);

        /** @var StateMachineFactory $stateMachineFactory */
        $stateMachineFactory = $this->app->make(StateMachineFactory::class);
        $stateMachineUpdater = new SessionStateMachineUpdater(
            $this->app->make(SessionRepositoryInterface::class),
            $this->createMock(Dispatcher::class),
            $stateMachineFactory,
            $this->app->make(TelephonySessionMetaUpdater::class),
            new MockClock(),
            $this->app->make(TransactionHandler::class)
        );
        $stateMachine = $stateMachineFactory->fromSession($telephonySession);

        // connect inbound channel to agent
        $stateMachine = $stateMachineUpdater->onEvent(
            new AudioConnectionChange(
                'caller-id',
                'agent-id',
                true
            ),
            $stateMachine
        );
        // connect agent to inbound channel
        $stateMachine = $stateMachineUpdater->onEvent(
            new AudioConnectionChange(
                'agent-id',
                'caller-id',
                true
            ),
            $stateMachine
        );

        $requestData = [
            'channelId' => 'company-id',
        ];
        $response = $this->postJson(route('admin.sc.api.agent-session.current.telephony.switchToChannel'), $requestData);
        $response->assertOk();

        $stateMachine = $stateMachineFactory->refresh($stateMachine);

        /** @var CanSendCommands $state */
        $state = $stateMachine->getState();

        $lastCommand = $state->getLastCommand();
        self::assertNotNull($lastCommand);
        self::assertSame(CommandState::STATE_DISPATCHED, $lastCommand->getState());
        self::assertSame('serviceCenterCommand', $lastCommand->getAction());

        $parameters = array_filter(
            $lastCommand->getParameters(),
            static function ($value): bool {
                return null !== $value;
            }
        );
        self::assertSame(['channelId' => 'company-id', 'command' => 'switchToChannel'], $parameters);
    }

    public function testItCanMuteAgent(): void
    {
        $this->loginAsAdmin();

        $agentSession = $this->givenUserHavingAgentSession($this->getLoggedInUser());
        $telephonySession = $this->givenTelephonySession(true, true);
        $this->givenAgentSessionHavingTelephonySession($agentSession, $telephonySession);

        /** @var StateMachineFactory $stateMachineFactory */
        $stateMachineFactory = $this->app->make(StateMachineFactory::class);
        $stateMachineUpdater = new SessionStateMachineUpdater(
            $this->app->make(SessionRepositoryInterface::class),
            $this->createMock(Dispatcher::class),
            $stateMachineFactory,
            $this->app->make(TelephonySessionMetaUpdater::class),
            new MockClock(),
            $this->app->make(TransactionHandler::class)
        );
        $stateMachine = $stateMachineFactory->fromSession($telephonySession);

        // connect inbound channel to agent
        $stateMachine = $stateMachineUpdater->onEvent(
            new AudioConnectionChange(
                'caller-id',
                'agent-id',
                true
            ),
            $stateMachine
        );
        // connect agent to inbound channel
        $stateMachine = $stateMachineUpdater->onEvent(
            new AudioConnectionChange(
                'agent-id',
                'caller-id',
                true
            ),
            $stateMachine
        );

        $response = $this->postJson(route('admin.sc.api.agent-session.current.telephony.muteAgent'));
        $response->assertOk();

        $stateMachine = $stateMachineFactory->refresh($stateMachine);

        /** @var CanSendCommands $state */
        $state = $stateMachine->getState();

        $lastCommand = $state->getLastCommand();
        self::assertNotNull($lastCommand);
        self::assertSame(CommandState::STATE_DISPATCHED, $lastCommand->getState());
        self::assertSame('serviceCenterCommand', $lastCommand->getAction());

        $parameters = array_filter(
            $lastCommand->getParameters(),
            static function ($value): bool {
                return null !== $value;
            }
        );
        self::assertSame(['channelId' => '', 'command' => 'switchToChannel'], $parameters);
    }

    private function givenTelephonySessionWithChannels(): TelephonySession
    {
        /** @var ServiceNumberLinkRepository $serviceNumberLinkRepository */
        $serviceNumberLinkRepository = $this->app->make(ServiceNumberLinkRepository::class);

        $serviceNumber = $this->createServiceNumber();

        // Configure the number as the main site number of a site without subscriptions
        $site = factory(Site::class)->create();
        $segment = factory(CallTrackingSegment::class)->create();
        $link = ServicenumberLink::makeSiteNumber($serviceNumber, $site, $segment);
        $serviceNumberLinkRepository->insert($link);

        /** @var SessionStateMachineUpdater $sessionStateMachineUpdater */
        $sessionStateMachineUpdater = $this->app->make(SessionStateMachineUpdater::class);
        /** @var TelephonySessionService $telephonySessionService */
        $telephonySessionService = $this->app->make(TelephonySessionService::class);
        /** @var SessionRepositoryInterface $sessionRepository */
        $sessionRepository = $this->app->make(SessionRepositoryInterface::class);

        // Simulate inbound call to the number
        $sessStateMachine = $telephonySessionService->createNewSessionStateMachine();
        $event = new ChannelCreated(
            'test',
            PhoneNumber::make('+31612345678'),
            $serviceNumber->getPhoneNumber(),
            ChannelState::DIRECTION_INBOUND,
            new ProviderInfo(Netwerkplek::LABEL),
            [ChannelMeta::REFERENCE => ChannelReferences::CALLER]
        );
        $sessionStateMachineUpdater->onEvent($event, $sessStateMachine);

        // The router should have picked up the matched number and delegated it to the service center
        return $sessionRepository->getById($sessStateMachine->getId());
    }

    private function createServiceNumber(): Servicenumber
    {
        /** @var ServiceNumberRepository $serviceNumberRepository */
        $serviceNumberRepository = $this->app->make(ServiceNumberRepository::class);

        // Create a service number
        $testNumber = PhoneNumber::make('+31881111111');
        $serviceNumber = Servicenumber::makeInstance(Netwerkplek::LABEL, $testNumber, 'NL');

        return $serviceNumberRepository->insert($serviceNumber);
    }

    private function givenAgentSessionHavingTelephonySession(
        AgentSession $agentSession,
        TelephonySession $telephonySession
    ): void {
        /** @var AgentSessionService $agentSessionService */
        $agentSessionService = $this->app->make(AgentSessionService::class);

        $agentSessionService->updateAgentSessionLogEntry(
            $agentSession,
            new AgentSessionStatus(AgentSessionStatus::AWAITING_CASE),
            null,
            $telephonySession
        );
    }
}
