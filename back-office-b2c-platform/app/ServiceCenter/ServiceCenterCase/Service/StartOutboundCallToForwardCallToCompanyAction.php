<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\Auth\User;
use App\Models\Office\Contactmethod;
use App\Repositories\Office\ContactMethodRepository;
use App\ServiceCenter\AgentSession\Assertion\AgentSessionAssertions;
use App\ServiceCenter\ServiceCenterCase\Assertion\ServiceCenterCaseAssertions;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StartOutboundCallToForwardCallToCompanyRequest;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\Telephony\ChannelReferences;
use App\ServiceCenter\Telephony\Factory\DerivedTelephonyStateFactory;
use App\Subscription\Service\SubscriptionValidator;
use App\Utils\Database\Contract\TransactionHandler;
use UnexpectedValueException;

class StartOutboundCallToForwardCallToCompanyAction
{
    private TransactionHandler $transactionHandler;
    private SubscriptionValidator $subscriptionValidator;
    private ContactMethodRepository $contactMethodRepository;
    private DerivedTelephonyStateFactory $derivedTelephonyStateFactory;
    private ServiceCenterCaseTelephonyService $caseTelephonyService;

    public function __construct(
        TransactionHandler $transactionHandler,
        SubscriptionValidator $subscriptionValidator,
        ContactMethodRepository $contactMethodRepository,
        DerivedTelephonyStateFactory $derivedTelephonyStateFactory,
        ServiceCenterCaseTelephonyService $caseTelephonyService
    ) {
        $this->transactionHandler = $transactionHandler;
        $this->subscriptionValidator = $subscriptionValidator;
        $this->contactMethodRepository = $contactMethodRepository;
        $this->derivedTelephonyStateFactory = $derivedTelephonyStateFactory;
        $this->caseTelephonyService = $caseTelephonyService;
    }

    public function handle(StartOutboundCallToForwardCallToCompanyRequest $request): void
    {
        $this->transactionHandler->transactional(function () use ($request) {
            $this->subscriptionValidator->assertSubscriptionValidFor(
                $request->getSubscription(),
                $request->getServiceType(),
                $this->contactMethodRepository->getOnlineById(Contactmethod::CALL),
                $request->getRegion()
            );

            $agent = $request->getAgent();

            AgentSessionAssertions::assertAgentHasActiveAgentSession($agent);
            self::assertAgentIsAssignedToCase($agent, $request->getCase());
            $this->assertAgentCanStartOutboundCall($agent);

            $subscription = $request->getSubscription();

            $phoneNumber = $subscription->getCurrentPhoneNumber();
            if (null === $phoneNumber) {
                $this->caseTelephonyService->getCompanyPhoneNumber($phoneNumber);
            }

            $this->caseTelephonyService->callCompany(
                $request->getCase(),
                $subscription->getCompany(),
                $subscription->getCurrentPhoneNumber()
            );
        });
    }

    private static function assertAgentIsAssignedToCase(User $agent, ServiceCenterCase $case): void
    {
        ServiceCenterCaseAssertions::assertThatCaseBelongsToAgentSession($case, $agent->getActiveAgentSession());
    }

    private function assertAgentCanStartOutboundCall(User $agent): void
    {
        AgentSessionAssertions::assertAgentSessionHasTelephonySession($agent->getActiveAgentSession());

        $telephonySession = $agent->getActiveAgentSession()->getAgentSessionLogEntry()->getTelephonySession();
        $telephonyState = $this->derivedTelephonyStateFactory->createFromTelephonySession($telephonySession);

        if ($telephonyState->hasChannelByReference(ChannelReferences::COMPANY)) {
            throw new UnexpectedValueException('Agent already has an outbound channel to a company');
        }

        if (1 !== $telephonyState->getActiveChannelCount()) {
            throw new UnexpectedValueException(
                'Agent can only start outbound call if there is one other channel active. Number of active channels: ' . $telephonyState->getActiveChannelCount()
            );
        }
    }
}
