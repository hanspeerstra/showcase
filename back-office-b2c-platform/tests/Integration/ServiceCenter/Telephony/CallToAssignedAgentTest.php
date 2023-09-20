<?php

declare(strict_types=1);

namespace Tests\Integration\ServiceCenter\Telephony;

use App\Auth\User;
use App\InternalPhone\InternalPhone;
use App\Models\Office\Site;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\Telephony\Number\Repository\ServiceNumberLinkRepository;
use App\Telephony\Number\Repository\ServiceNumberRepository;
use App\Telephony\Number\Servicenumber;
use App\Telephony\Number\ServicenumberLink;
use App\Telephony\Provider\Netwerkplek\Netwerkplek;
use App\Telephony\Provider\Netwerkplek\NetwerkplekFlowClientInterface;
use App\Telephony\Session\Event\Channel\ChannelCreated;
use App\Telephony\Session\ProviderInfo;
use App\Telephony\Session\Repository\SessionRepositoryInterface;
use App\Telephony\Session\SessionStateMachineUpdater;
use App\Telephony\Session\State\Sub\ChannelState;
use App\Telephony\Session\TelephonySessionService;
use App\Telephony\Tracking\Models\CallTrackingSegment;
use App\Utils\Time\ClockInterface;
use App\Utils\Time\MockClock;
use BaseTestSeeder;
use Carbon\Carbon;
use Propaganistas\LaravelPhone\PhoneNumber;
use TestMygoSeeder;
use Tests\Integration\IntegrationTestCase;
use Tests\Mocks\Telephony\Provider\Netwerkplek\MockNetwerkplekFlowClient;
use TestSiteSeeder;

class CallToAssignedAgentTest extends IntegrationTestCase
{
    /** @var SessionStateMachineUpdater */
    private $updater;
    /** @var TelephonySessionService */
    private $sessionService;
    /** @var ServiceNumberLinkRepository */
    private $numberLinkRepo;
    /** @var ServiceCenterCaseRepository */
    private $caseRepo;
    /** @var SessionRepositoryInterface */
    private $sessionRepo;
    /** @var Servicenumber */
    private $serviceNumber;
    /** @var ServiceCenterCaseService */
    private $caseService;
    /** @var MockNetwerkplekFlowClient */
    private $flowClient;
    /** @var AgentSessionService|mixed */
    private $agentSessionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([BaseTestSeeder::class, TestSiteSeeder::class, TestMygoSeeder::class]);

        // Fix the time so that the SC is opened
        $clock = new MockClock(Carbon::parse('2020-01-01T12:00'));
        $this->app->instance(ClockInterface::class, $clock);

        $this->sessionRepo = $this->app->make(SessionRepositoryInterface::class);
        $this->caseRepo = $this->app->make(ServiceCenterCaseRepository::class);
        $this->updater = $this->app->make(SessionStateMachineUpdater::class);
        $this->sessionService = $this->app->make(TelephonySessionService::class);
        $this->caseService = $this->app->make(ServiceCenterCaseService::class);
        $this->agentSessionService = $this->app->make(AgentSessionService::class);

        $serviceNumberRepo = $this->app->make(ServiceNumberRepository::class);
        $this->numberLinkRepo = $this->app->make(ServiceNumberLinkRepository::class);

        // Replace Netwerkplek client with mock implementation
        $this->flowClient = new MockNetwerkplekFlowClient();
        $this->app->instance(NetwerkplekFlowClientInterface::class, $this->flowClient);

        // Create a service number
        $testNumber = PhoneNumber::make('+31881111111');
        $this->serviceNumber = Servicenumber::makeInstance(Netwerkplek::LABEL, $testNumber, 'NL');
        $serviceNumberRepo->insert($this->serviceNumber);
    }

    public function testAgentGetsCalledOnAssignedTelephonyCase(): void
    {
        // Configure the number as the main site number of a site without subscriptions
        $site = Site::findByName('test.test');
        $segment = CallTrackingSegment::hasLabel(CallTrackingSegment::LABEL_STANDARD)->first();
        $link = ServicenumberLink::makeSiteNumber($this->serviceNumber, $site, $segment);
        $this->numberLinkRepo->insert($link);

        // Simulate inbound call to the number
        $sessStateMachine = $this->sessionService->createNewSessionStateMachine();
        $event = new ChannelCreated(
            'test',
            null,
            $this->serviceNumber->getPhoneNumber(),
            ChannelState::DIRECTION_INBOUND,
            new ProviderInfo(Netwerkplek::LABEL)
        );
        $this->updater->onEvent($event, $sessStateMachine);

        // The router should have picked up the matched number and delegated it to the service center
        $session = $this->sessionRepo->getById($sessStateMachine->getId());
        $case = $this->caseRepo->tryGetBySourceTelephonySession($session);

        self::assertNotNull($case);

        // Now assign the case to an agent
        $user = factory(User::class)->create();
        /** @var InternalPhone $internalPhone */
        $internalPhone = factory(InternalPhone::class)->create();
        $agentSession = $this->agentSessionService->createAndStartSession(
            $user,
            $internalPhone,
            false,
            null,
        );

        $this->caseService->startCase($case, $agentSession);

        $call = $session->getCalls()->first();

        // Check that a command was sent to Netwerkplek to call to the agent
        $expectedCommandHistory = [
            [
                'callId' => $session->getId(),
                'action' => 'inboundCallCommand',
                'data' => [
                    'command' => 'toServiceCenter',
                    'recordMode' => 'full',
                    'recordingName' => 'MM SC call ' . $call->getId(),
                ],
            ],
            [
                'callId' => $session->getId(),
                'action' => 'serviceCenterCommand',
                'data' => [
                    'source' => TestMygoSeeder::PHONE_NUMBER,
                    'destination' => $internalPhone->getExternal(),
                    'command' => 'connectToAgent',
                    'agentSessionId' => $agentSession->getId(),
                    'reference' => null,
                    'companyId' => null,
                ],
            ],
        ];
        self::assertEquals($expectedCommandHistory, $this->flowClient->history);
    }
}
