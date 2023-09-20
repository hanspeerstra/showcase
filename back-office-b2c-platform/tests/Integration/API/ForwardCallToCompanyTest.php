<?php

declare(strict_types=1);

namespace Tests\Integration\API;

use App\Auth\User;
use App\Models\Office\Company;
use App\Models\Office\Contactmethod;
use App\Models\Office\Contract;
use App\Models\Office\Pricing;
use App\Models\Office\Profession;
use App\Models\Office\ProfessionPricing;
use App\Models\Office\Region;
use App\Models\Office\Servicetype;
use App\Models\Office\Site;
use App\Models\Office\Subscription;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\ServiceCenterCase\CaseType;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Telephony\Number\Repository\ServiceNumberLinkRepository;
use App\Telephony\Number\ServicenumberLink;
use App\Telephony\Provider\ProviderFactory;
use App\Telephony\Provider\ProviderInterface;
use App\Telephony\Session\Event\Channel\AudioConnectionChange;
use App\Telephony\Session\Event\Channel\ChannelStateSwitch;
use App\Telephony\Session\Model\TelephonySession;
use App\Telephony\Session\SessionStateMachineUpdater;
use App\Telephony\Session\State\Sub\ChannelState;
use App\Telephony\Session\StateMachineFactory;
use App\Telephony\Tracking\Models\CallTrackingSegment;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\MockObject\MockObject;
use Propaganistas\LaravelPhone\PhoneNumber;
use Tests\Factories\ContractFactory;
use Tests\Factories\ContractRuleFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\PostcodeFactory;
use Tests\Factories\ProfessionFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Integration\Concerns\InteractsWithAgentSession;
use Tests\Integration\Concerns\InteractsWithTelephonySession;
use Tests\Integration\IntegrationTestCase;

class ForwardCallToCompanyTest extends IntegrationTestCase
{
    use WithFaker;
    use InteractsWithTelephonySession;
    use InteractsWithAgentSession;

    /** @var User */
    private $agent;

    /** @var Site */
    private $site;

    /** @var Site */
    private $dedicatedSite;

    /** @var Site */
    private $pplSite;

    /** @var Region */
    private $region;

    /** @var Servicetype */
    private $serviceType;

    /** @var Contactmethod */
    private $contactMethod;

    private $sentCommands = [];

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ProviderInterface|MockObject $provider */
        $provider = $this->createMock(ProviderInterface::class);
        $provider
            ->method('sendCommand')
            ->willReturnCallback(function (int $callId, string $action, array $parameters): void {
                $this->sentCommands[] = [$callId, $action, $parameters];
            });

        /** @var ProviderFactory|MockObject $providerFactory */
        $providerFactory = $this->createMock(ProviderFactory::class);
        $providerFactory
            ->method('getByLabel')
            ->willReturn($provider);

        $this->app->instance(ProviderFactory::class, $providerFactory);

        $this->agent = $this->loginAsAdmin();

        $this->site = factory(Site::class)->create(['name' => 'mygo.nl']);

        /** @var Profession $profession */
        $profession = app(ProfessionFactory::class)->withServicetypes(1)->create();
        $this->serviceType = $profession->servicetypes()->first();

        $this->region = factory(Region::class)->create();
        $this->app->make(PostcodeFactory::class)
            ->forRegion($this->region)
            ->create();

        $this->dedicatedSite = factory(Site::class)->create(['city_id' => $this->region->getFirstCity()]);
        $this->pplSite = factory(Site::class)->create(['city_id' => $this->region->getFirstCity()]);

        $this->contactMethod = Contactmethod::findOrFail(Contactmethod::CALL);

        $this->givenMygoHavingMainSiteNumber();
    }

    public function testItCanStartOutboundCall(): void
    {
        $case = $this->givenAssignedCase();

        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $telephonySession = $this->givenTelephonySession(
            true,
            false,
            '+31611223344',
            '+31610000002',
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = $this->getStartOutboundCallRequestData($company->getActiveMatchmakerSubscription());

        $response = $this->postJson(
            route('admin.sc.api.cases.startOutboundCallToForwardToCompany', [$case->getId()]),
            $data
        );

        $response->assertSuccessful();

        $this->assertScCommandSent('startOutboundCall');
    }

    public function testItCanStartOutboundCallUsingDedicatedSubscription(): void
    {
        $case = $this->givenAssignedCase();

        $subscription = $this->givenCompanyHavingDedicatedSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $telephonySession = $this->givenTelephonySession(
            true,
            false,
            '+31611223344',
            '+31610000002',
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = $this->getStartOutboundCallRequestData($subscription);

        $response = $this->postJson(
            route('admin.sc.api.cases.startOutboundCallToForwardToCompany', [$case->getId()]),
            $data
        );

        $response->assertSuccessful();

        $this->assertScCommandSent('startOutboundCall');
    }

    public function testItCanStartOutboundCallUsingPplSubscription(): void
    {
        $case = $this->givenAssignedCase();

        $subscription = $this->givenCompanyHavingPplSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $telephonySession = $this->givenTelephonySession(
            true,
            false,
            '+31611223344',
            '+31610000002',
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = $this->getStartOutboundCallRequestData($subscription);

        $response = $this->postJson(
            route('admin.sc.api.cases.startOutboundCallToForwardToCompany', [$case->getId()]),
            $data
        );

        $response->assertSuccessful();

        $this->assertScCommandSent('startOutboundCall');
    }

    public function testItCannotStartOutboundCallIfCompanyHasInsufficientFunds(): void
    {
        $case = $this->givenAssignedCase();

        $company = $this->givenCompanyHavingMatchmakerSubscription('10.00');
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing('30.00');

        $telephonySession = $this->givenTelephonySession(
            true,
            false,
            '+31611223344',
            '+31610000002',
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = $this->getStartOutboundCallRequestData($company->getActiveMatchmakerSubscription());

        $response = $this->postJson(
            route('admin.sc.api.cases.startOutboundCallToForwardToCompany', [$case->getId()]),
            $data
        );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors('subscriptionId');
    }

    public function testItCannotStartOutboundCallIfAlreadyHasAnOutboundChannel(): void
    {
        $case = $this->givenAssignedCase();

        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $telephonySession = $this->givenTelephonySession(
            true,
            true,
            '+31611223344',
            '+31610000002',
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = $this->getStartOutboundCallRequestData($company->getActiveMatchmakerSubscription());

        $response = $this->postJson(
            route('admin.sc.api.cases.startOutboundCallToForwardToCompany', [$case->getId()]),
            $data
        );

        $response->assertStatus(500);

        $response->assertSeeText('Agent already has an outbound channel to a company');
    }

    public function testItCannotStartOutboundCallIfHasNoInboundChannel(): void
    {
        $case = $this->givenAssignedCase();

        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $telephonySession = $this->givenTelephonySession(
            false,
            true,
            '+31611223344',
            '+31610000002',
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = $this->getStartOutboundCallRequestData($company->getActiveMatchmakerSubscription());

        $response = $this->postJson(
            route('admin.sc.api.cases.startOutboundCallToForwardToCompany', [$case->getId()]),
            $data
        );

        $response->assertStatus(500);

        $response->assertSeeText(
            'Agent can only start outbound call if there is one other channel active. Number of active channels: 0'
        );
    }

    public function testItCanForwardCallToCompany(): void
    {
        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $company->refresh();

        $case = $this->givenAssignedCase(false);

        $telephonySession = $this->givenTelephonySession(
            true,
            true,
            '+31611223344',
            $company->getActiveMatchmakerSubscription()->getCurrentPhoneNumber()->formatE164(),
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = $this->getForwardCallRequestData($company->getActiveMatchmakerSubscription());

        $response = $this->postJson(route('admin.sc.api.cases.forwardCallToCompany', [$case->getId()]), $data);

        $response->assertSuccessful();

        $this->assertScCommandSent('forwardCallToOutboundChannel');

        $this->assertDatabaseHas('leads', [
            'approved' => false,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', []);

        $case->refresh();

        self::assertNotNull($case->getLead());

        $this->assertDatabaseHas('calls', [
            'lead_id' => $case->getLead()->getId(),
            'calling_number' => '+31611223344',
            'forwarding_number' => $company->getActiveMatchmakerSubscription()->getCurrentPhoneNumber()->formatE164(),
            'duration' => null,
        ]);

        $stateMachineFactory = $this->app->make(StateMachineFactory::class);
        $stateMachine = $stateMachineFactory->fromSession($telephonySession->refresh());

        $stateMachineUpdater = $this->app->make(SessionStateMachineUpdater::class);
        $stateMachine = $stateMachineUpdater->onEvent(
            new AudioConnectionChange(
                'caller-id',
                'company-id',
                true
            ),
            $stateMachine
        );
        $stateMachine = $stateMachineUpdater->onEvent(
            new AudioConnectionChange(
                'company-id',
                'caller-id',
                true
            ),
            $stateMachine
        );
        // Agent gets disconnected from session after forward
        $stateMachineUpdater->onEvent(
            new ChannelStateSwitch(
                'agent-id',
                ChannelState::STATE_HANGUP,
                null,
                ChannelState::HANGUP_INITIATOR_REMOTE
            ),
            $stateMachine
        );

        $agentSession = $this->agent->getActiveAgentSession();
        $agentSession->refresh();

        self::assertFalse($agentSession->hasActiveTelephonySession());
        self::assertNull($agentSession->getAgentSessionLogEntry()->getTelephonySession());

        $this->app->make(ServiceCenterCaseService::class)
            ->closeCase($case);
        $this->addToAssertionCount(1); // can close case after forward
    }

    public function testItCanForwardCallToCompanyUsingDedicatedSubscription(): void
    {
        $case = $this->givenAssignedCase();

        $subscription = $this->givenCompanyHavingDedicatedSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $telephonySession = $this->givenTelephonySession(
            true,
            true,
            '+31611223344',
            $subscription->getCurrentPhoneNumber()->formatE164(),
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = $this->getForwardCallRequestData($subscription);

        $response = $this->postJson(route('admin.sc.api.cases.forwardCallToCompany', [$case->getId()]), $data);

        $response->assertSuccessful();

        $this->assertScCommandSent('forwardCallToOutboundChannel');

        $this->assertDatabaseHas('leads', [
            'approved' => false,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', []);

        $case->refresh();

        self::assertNotNull($case->getLead());

        $this->assertDatabaseHas('calls', [
            'lead_id' => $case->getLead()->getId(),
            'calling_number' => '+31611223344',
            'forwarding_number' => $subscription->getCurrentPhoneNumber()->formatE164(),
            'duration' => null,
        ]);
    }

    public function testItCanForwardCallToCompanyUsingPplSubscription(): void
    {
        $case = $this->givenAssignedCase();

        $subscription = $this->givenCompanyHavingPplSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $telephonySession = $this->givenTelephonySession(
            true,
            true,
            '+31611223344',
            $subscription->getCurrentPhoneNumber()->formatE164(),
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = $this->getForwardCallRequestData($subscription);

        $response = $this->postJson(route('admin.sc.api.cases.forwardCallToCompany', [$case->getId()]), $data);

        $response->assertSuccessful();

        $this->assertScCommandSent('forwardCallToOutboundChannel');

        $this->assertDatabaseHas('leads', [
            'approved' => false,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', []);

        $case->refresh();

        self::assertNotNull($case->getLead());

        $this->assertDatabaseHas('calls', [
            'lead_id' => $case->getLead()->getId(),
            'calling_number' => '+31611223344',
            'forwarding_number' => $subscription->getCurrentPhoneNumber()->formatE164(),
            'duration' => null,
        ]);
    }

    public function testItCanForwardCallToCompanyUsingNoCharge(): void
    {
        $case = $this->givenAssignedCase();

        $subscription = $this->givenCompanyHavingPplSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $telephonySession = $this->givenTelephonySession(
            true,
            true,
            '+31611223344',
            $subscription->getCurrentPhoneNumber()->formatE164(),
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = $this->getForwardCallRequestData($subscription);
        $data['noCharge'] = true;

        $response = $this->postJson(route('admin.sc.api.cases.forwardCallToCompany', [$case->getId()]), $data);

        $response->assertSuccessful();

        $this->assertScCommandSent('forwardCallToOutboundChannel');

        $this->assertDatabaseHas('leads', [
            'approved' => false,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', []);

        $case->refresh();

        self::assertNotNull($case->getLead());
        self::assertEquals('0.00', $case->getLead()->getLeadPrice());

        $this->assertDatabaseHas('calls', [
            'lead_id' => $case->getLead()->getId(),
            'calling_number' => '+31611223344',
            'forwarding_number' => $subscription->getCurrentPhoneNumber()->formatE164(),
            'duration' => null,
        ]);
    }

    public function testItCannotForwardCallToCompanyIfNotInCallWithCompany(): void
    {
        $case = $this->givenAssignedCase();

        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $company->refresh();

        $telephonySession = $this->givenTelephonySession(
            true,
            false,
            '+31611223344',
            '+31610000002',
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = $this->getForwardCallRequestData($company->getActiveMatchmakerSubscription());

        $response = $this->postJson(route('admin.sc.api.cases.forwardCallToCompany', [$case->getId()]), $data);

        $response->assertStatus(500);
        $response->assertSeeText('Agent does not have an outbound channel to a company');
    }

    public function testItCannotForwardCallToCompanyIfPhoneNumberDoesNotMatch(): void
    {
        $case = $this->givenAssignedCase();

        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $company->refresh();

        $telephonySession = $this->givenTelephonySession(
            true,
            true,
            '+31611223344',
            '+31850102034',
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = $this->getForwardCallRequestData($company->getActiveMatchmakerSubscription());

        $response = $this->postJson(route('admin.sc.api.cases.forwardCallToCompany', [$case->getId()]), $data);

        $response->assertStatus(500);

        $response->assertSeeText(
            json_encode(
                'Phone number "+31850102034" does not belong to company ' . $company->getId()
            )
        );
    }

    public function testItCanHangupCompanyChannel(): void
    {
        $this->givenAssignedCase();
        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $company->refresh();

        $telephonySession = $this->givenTelephonySession(
            true,
            true,
            '+31611223344',
            $company->getActiveMatchmakerSubscription()->getCurrentPhoneNumber()->formatE164(),
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = [
            'channelId' => 'company-id',
        ];
        $response = $this->postJson(route('admin.sc.api.agent-session.current.telephony.hangupChannel'), $data);

        $response->assertSuccessful();

        $this->assertScCommandSent('hangupChannel');
    }

    public function testItCannotHangupCompanyIfNotInCallWithCompany(): void
    {
        $this->givenAssignedCase();
        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $company->refresh();

        $telephonySession = $this->givenTelephonySession(
            true,
            false,
            '+31611223344',
            '+31610000002',
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $data = [
            'channelId' => '100',
        ];
        $response = $this->postJson(route('admin.sc.api.agent-session.current.telephony.hangupChannel'), $data);

        $response->assertJsonValidationErrors('channelId');
    }

    public function testItCanHangupCompanyByCase(): void
    {
        $case = $this->givenAssignedCase();
        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $company->refresh();

        $telephonySession = $this->givenTelephonySession(
            true,
            true,
            '+31611223344',
            $company->getActiveMatchmakerSubscription()->getCurrentPhoneNumber()->formatE164(),
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $response = $this->postJson(route('admin.sc.api.cases.forwardCallToCompanyHangup', ['case' => $case]));

        $response->assertSuccessful();

        $this->assertScCommandSent('hangupChannel');
    }

    public function testItCannotHangupCompanyIfNotInCallWithCompanyByCase(): void
    {
        $case = $this->givenAssignedCase();
        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $company->refresh();

        $telephonySession = $this->givenTelephonySession(
            true,
            false,
            '+31611223344',
            '+31610000002',
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $response = $this->postJson(route('admin.sc.api.cases.forwardCallToCompanyHangup', ['case' => $case]));

        $response->assertStatus(500);
        $response->assertSeeText('Agent does not have an outbound channel to a company');
    }

    public function testItCanHangup(): void
    {
        $this->givenAssignedCase();
        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $company->refresh();

        $telephonySession = $this->givenTelephonySession(
            true,
            false,
            '+31611223344',
            '+31610000002',
            $this->getLoggedInUser()->getActiveAgentSession()->getId()
        );

        $this->givenAgentHasTelephonySession($telephonySession);

        $response = $this->postJson(route('admin.sc.api.agent-session.current.telephony.hangup'));

        $response->assertSuccessful();

        $this->assertScCommandSent('hangupChannel');
    }

    public function testItCanStartTelephonySession(): void
    {
        $case = $this->givenAssignedCase(false);

        $this->givenServiceCenterSourceNumbers();

        $response = $this->postJson(route('admin.sc.api.cases.startTelephonySession', ['case' => $case]));

        $response->assertSuccessful();

        $case->refresh();

        self::assertNotNull($case->getActiveTelephonySession());
        self::assertNotNull($case->getCurrentAgentSessionLogEntry()->getTelephonySession());
        self::assertSame(
            $case->getActiveTelephonySession()->getKey(),
            $case->getCurrentAgentSessionLogEntry()->getTelephonySession()->getKey()
        );

        $this->assertCommandSent('startOutboundScSession');
    }

    public function testItCanStartOutboundCallToCustomerAfterStartingTelephonySession(): void
    {
        $case = $this->givenAssignedCase(false);

        $this->givenServiceCenterSourceNumbers();

        $this->postJson(route('admin.sc.api.cases.startTelephonySession', ['case' => $case]));

        $requestData = [
            'phoneNumber' => PhoneNumber::make('+31612345678')->formatE164(),
        ];

        $response = $this->postJson(
            route('admin.sc.api.cases.startOutboundCallToCustomer', ['case' => $case]),
            $requestData
        );

        $response->assertSuccessful();

        $this->assertScCommandSent('startOutboundCall');
    }

    public function testItCanStartOutboundCallToCompanyAfterStartingTelephonySession(): void
    {
        $case = $this->givenAssignedCase(false);

        $this->givenServiceCenterSourceNumbers();

        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $company->refresh();

        $this->postJson(route('admin.sc.api.cases.startTelephonySession', ['case' => $case]));

        $requestData = [
            'companyId' => $company->getId(),
        ];

        $response = $this->postJson(
            route('admin.sc.api.cases.startOutboundCallToCompany', ['case' => $case]),
            $requestData
        );

        $response->assertSuccessful();

        $this->assertScCommandSent('startOutboundCall');
    }

    public function testItCanForwardCallToCompanyAfterStartingOutboundCallToCustomerAndCompanyDirect(): void
    {
        $case = $this->givenAssignedCase(false);

        $this->givenServiceCenterSourceNumbers();

        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $company->refresh();

        $this->getLoggedInUser()->refresh();
        $this->postJson(route('admin.sc.api.cases.startTelephonySession', ['case' => $case]));

        $case->refresh();

        $this->getLoggedInUser()->refresh();
        $requestData = [
            'phoneNumber' => '+31610000001',
        ];
        $this->postJson(route('admin.sc.api.cases.startOutboundCallToCustomer', ['case' => $case]), $requestData);

        $this->givenOutboundCustomerChannel(
            $case->getActiveTelephonySession(),
            '+31610000001'
        );

        $requestData = [
            'companyId' => $company->getId(),
        ];

        $this->getLoggedInUser()->refresh();
        $this->postJson(route('admin.sc.api.cases.startOutboundCallToCompany', ['case' => $case]), $requestData);

        $this->givenCompanyChannel(
            $case->getActiveTelephonySession(),
            $company,
            $company->getActiveMatchmakerSubscription()->getCurrentPhoneNumber()->formatE164()
        );

        $data = $this->getForwardCallRequestData($company->getActiveMatchmakerSubscription());

        $this->getLoggedInUser()->refresh();
        $response = $this->postJson(route('admin.sc.api.cases.forwardCallToCompany', [$case->getId()]), $data);

        $response->assertSuccessful();

        $this->assertScCommandSent('forwardCallToOutboundChannel');

        $this->assertDatabaseHas('leads', [
            'approved' => false,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', []);

        $case->refresh();

        self::assertNotNull($case->getLead());

        $this->assertDatabaseHas('calls', [
            'lead_id' => $case->getLead()->getId(),
            'calling_number' => '+31610000001',
            'forwarding_number' => $company->getActiveMatchmakerSubscription()->getCurrentPhoneNumber()->formatE164(),
            'duration' => null,
        ]);
    }

    public function testItCanForwardCallToCompanyAfterStartingOutboundCallToCustomerAndCompany(): void
    {
        $case = $this->givenAssignedCase(false);

        $this->givenServiceCenterSourceNumbers();

        $company = $this->givenCompanyHavingMatchmakerSubscription();
        $this->givenSubscriptionsHaveContract();
        $this->givenContractsHaveContractRule();
        $this->givenPricing();

        $company->refresh();

        $this->getLoggedInUser()->refresh();
        $this->postJson(route('admin.sc.api.cases.startTelephonySession', ['case' => $case]));

        $case->refresh();

        $this->getLoggedInUser()->refresh();
        $requestData = [
            'phoneNumber' => '+31610000001',
        ];
        $this->postJson(route('admin.sc.api.cases.startOutboundCallToCustomer', ['case' => $case]), $requestData);

        $this->givenOutboundCustomerChannel(
            $case->getActiveTelephonySession(),
            '+31610000001'
        );

        $requestData = $this->getStartOutboundCallRequestData($company->getActiveMatchmakerSubscription());

        $this->getLoggedInUser()->refresh();
        $this->postJson(
            route('admin.sc.api.cases.startOutboundCallToForwardToCompany', ['case' => $case]),
            $requestData
        );

        $this->givenCompanyChannel(
            $case->getActiveTelephonySession(),
            $company,
            $company->getActiveMatchmakerSubscription()->getCurrentPhoneNumber()->formatE164()
        );

        $data = $this->getForwardCallRequestData($company->getActiveMatchmakerSubscription());

        $this->getLoggedInUser()->refresh();
        $response = $this->postJson(route('admin.sc.api.cases.forwardCallToCompany', [$case->getId()]), $data);

        $response->assertSuccessful();

        $this->assertScCommandSent('forwardCallToOutboundChannel');

        $this->assertDatabaseHas('leads', [
            'approved' => false,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', []);

        $case->refresh();

        self::assertNotNull($case->getLead());

        $this->assertDatabaseHas('calls', [
            'lead_id' => $case->getLead()->getId(),
            'calling_number' => '+31610000001',
            'forwarding_number' => $company->getActiveMatchmakerSubscription()->getCurrentPhoneNumber()->formatE164(),
            'duration' => null,
        ]);
    }

    public function testItCanForwardCallToCompanyAfterStartingOutboundCallToCustomerAndUnknownCompany(): void
    {
        $case = $this->givenAssignedCase(false);

        $this->givenServiceCenterSourceNumbers();

        $this->getLoggedInUser()->refresh();
        $this->postJson(route('admin.sc.api.cases.startTelephonySession', ['case' => $case]));

        $case->refresh();

        $this->getLoggedInUser()->refresh();
        $requestData = [
            'phoneNumber' => '+31610000001',
        ];
        $this->postJson(route('admin.sc.api.cases.startOutboundCallToCustomer', ['case' => $case]), $requestData);

        $this->givenOutboundCustomerChannel(
            $case->getActiveTelephonySession(),
            '+31610000001'
        );

        $this->getLoggedInUser()->refresh();
        $requestData = [
            'phoneNumber' => '+31610000123',
        ];
        $this->postJson(route('admin.sc.api.cases.startOutboundCallToUnknownCompany', ['case' => $case]), $requestData);

        $this->givenCompanyChannel(
            $case->getActiveTelephonySession(),
            null,
            '+31610000123'
        );

        $this->getLoggedInUser()->refresh();
        $requestData = [
            'serviceTypeId' => $this->serviceType->getId(),
            'regionId' => $this->region->getId(),
        ];
        $response = $this->postJson(
            route('admin.sc.api.cases.forwardCallToUnknownCompany', [$case->getId()]),
            $requestData
        );

        echo $response->getContent(), "\n";

        $response->assertSuccessful();

        $this->assertScCommandSent('forwardCallToOutboundChannel');

        $this->assertDatabaseMissing('leads', []);
        $this->assertDatabaseMissing('wallet_transactions', []);

        $case->refresh();

        self::assertNull($case->getLead());

        $this->assertDatabaseHas('calls', [
            'lead_id' => null,
            'calling_number' => '+31610000001',
            'forwarding_number' => '+31610000123',
            'duration' => null,
        ]);
    }

    private function assertCommandSent(string $action): void
    {
        $sentCommands = array_map(
            static function (array $command): string {
                return $command[1];
            },
            $this->sentCommands
        );

        self::assertContains($action, $sentCommands);
    }

    private function assertScCommandSent(string $action): void
    {
        $sentActions = array_map(
            static function (array $command): ?string {
                if (isset($command[2]['command'])) {
                    return $command[2]['command'];
                }

                return null;
            },
            $this->sentCommands
        );

        $sentActions = array_filter($sentActions, static function (?string $command): bool {
            return null !== $command;
        });

        self::assertContains($action, $sentActions);
    }

    private function getForwardCallRequestData(
        Subscription $subscription
    ): array {
        return [
            'subscriptionId' => $subscription->getId(),
            'regionId' => $this->region->getId(),
            'serviceTypeId' => $this->serviceType->getId(),
        ];
    }

    private function getStartOutboundCallRequestData(
        Subscription $subscription
    ): array {
        return [
            'subscriptionId' => $subscription->getId(),
            'regionId' => $this->region->getId(),
            'serviceTypeId' => $this->serviceType->getId(),
        ];
    }

    private function givenMygoHavingMainSiteNumber(): void
    {
        $mygo = Site::getDefaultSite();

        $segment = CallTrackingSegment::hasLabel(CallTrackingSegment::LABEL_STANDARD)->firstOrFail();

        $serviceNumber = $this->createServiceNumber();

        $link = ServicenumberLink::makeSiteNumber($serviceNumber, $mygo, $segment);

        $this->app->make(ServiceNumberLinkRepository::class)
            ->insert($link);
    }

    private function givenServiceCenterSourceNumbers(): void
    {
        /** @var ServiceNumberLinkRepository $repo */
        $repo = $this->app->make(ServiceNumberLinkRepository::class);

        foreach (ServicenumberLink::SYSTEM_LABELS as $label) {
            $repo->insert(ServicenumberLink::makeSystemNumber($this->createServiceNumber(), $label));
        }
    }

    private function givenAgentHasTelephonySession(TelephonySession $telephonySession): void
    {
        $agentSession = $this->agent->getActiveAgentSession();
        $currentLogEntry = $agentSession->getAgentSessionLogEntry();

        $this->app->make(AgentSessionService::class)
            ->updateAgentSessionLogEntry(
                $agentSession,
                $currentLogEntry->getStatus(),
                $currentLogEntry->getServiceCenterCase(),
                $telephonySession
            );

        $agentSession->refresh(); // needed for some reason, probably some caching going on somewhere
    }

    private function givenAssignedCase(
        bool $hasTelephonySession = true,
        TelephonySession $telephonySession = null
    ): ServiceCenterCase {
        if (null === $telephonySession && $hasTelephonySession) {
            $telephonySession = $this->givenTelephonySession(true, false);
        }

        $case = ServiceCenterCase::makeInstance(
            factory(CaseType::class)->create(),
            factory(WorkGroup::class)->create(),
            null,
            null,
            $telephonySession,
            null !== $telephonySession ? $this->getServiceNumberLink() : null
        );

        $case = $this->app->make(ServiceCenterCaseRepository::class)
            ->persist($case);

        $agentSession = $this->givenUserHavingAgentSession($this->getLoggedInUser());

        $this->app->make(ServiceCenterCaseService::class)
            ->startCase($case, $agentSession);

        $case->refresh();

        return $case;
    }

    public function givenPricing(string $price = '10.0'): void
    {
        $pricing = new Pricing([
            'servicetype_id' => $this->serviceType->getId(),
            'contactmethod_id' => $this->contactMethod->getId(),
            'region_id' => $this->region->getId(),
            'price' => $price,
            'activeFrom' => now()->subDay(),
        ]);

        $pricing->save();

        $professionPricing = new ProfessionPricing([
            'profession_id' => $this->serviceType->getProfession()->getId(),
            'contactmethod_id' => $this->contactMethod->getId(),
            'price' => $price,
        ]);

        $professionPricing->save();
    }

    private function givenSubscriptionsHaveContract(): void
    {
        Subscription::all()->each(function (Subscription $subscription) {
            $site = null;
            if ($subscription->isDedicated()) {
                $site = $this->dedicatedSite;
            } elseif ($subscription->isPpl()) {
                $site = $this->pplSite;
            } else {
                $site = $this->site;
            }

            app(ContractFactory::class)
                ->states('online')
                ->forSite($site)
                ->forSubscription($subscription)
                ->create();
        });
    }

    private function givenContractsHaveContractRule(): void
    {
        Contract::all()->each(function (Contract $contract) {
            app(ContractRuleFactory::class)
                ->forRegion($this->region)
                ->forServicetype($this->serviceType)
                ->forContract($contract)
                ->create();
        });
    }

    private function givenCompanyHavingMatchmakerSubscription(string $balance = '999.99'): Company
    {
        $company = $this->givenCompany();

        /** @var Subscription $subscription */
        $subscription = app(SubscriptionFactory::class)
            ->states(['online', 'matchmaker'])
            ->forCompany($company)
            ->create([
                'amount_per_invoice' => $balance,
            ]);

        $subscription->contactmethods()->sync(
            Contactmethod::all()
        );

        // Generate an unpaid invoice.
        // This will increase the spending_limit with the amount_per_invoice on the subscription.
        app(InvoiceFactory::class)
            ->forSubscription($subscription)
            ->forAmount($subscription->getAmountPerInvoice())
            ->outstanding()
            ->create();

        return $company;
    }

    private function givenCompanyHavingDedicatedSubscription(): Subscription
    {
        $company = $this->givenCompany();

        $subscription = app(SubscriptionFactory::class)
            ->states(['online', Subscription::TYPE_DEDICATED])
            ->forCompany($company)
            ->create();

        $subscription->contactmethods()->sync(
            Contactmethod::all()
        );

        return $subscription;
    }

    private function givenCompanyHavingPplSubscription(string $balance = '999.99'): Subscription
    {
        $company = $this->givenCompany();

        /** @var Subscription $subscription */
        $subscription = app(SubscriptionFactory::class)
            ->states(['online', Subscription::TYPE_PPL])
            ->forCompany($company)
            ->create([
                'amount_per_invoice' => $balance,
            ]);

        $subscription->contactmethods()->sync(
            Contactmethod::all()
        );

        // Generate an unpaid invoice.
        // This will increase the spending_limit with the amount_per_invoice on the subscription.
        app(InvoiceFactory::class)
            ->forSubscription($subscription)
            ->forAmount($subscription->getAmountPerInvoice())
            ->outstanding()
            ->create();

        return $subscription;
    }

    private function givenCompany(): Company
    {
        return factory(Company::class)
            ->create([
                'user_id' => factory(User::class)->create(),
            ]);
    }
}
