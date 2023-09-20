<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\Auth\User;
use App\Models\Office\Company;
use App\ServiceCenter\AgentSession\Assertion\AgentSessionAssertions;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\Telephony\ChannelReferences;
use App\ServiceCenter\Telephony\DerivedChannelState;
use App\ServiceCenter\Telephony\Factory\DerivedTelephonyStateFactory;
use App\ServiceCenter\Telephony\Service\TelephonyService;
use App\Telephony\Call\CallService;
use App\Telephony\Number\Repository\ServiceNumberLinkRepository;
use App\Telephony\Number\ServicenumberLink;
use App\Utils\Database\Contract\TransactionHandler;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Propaganistas\LaravelPhone\PhoneNumber;
use UnexpectedValueException;

class ServiceCenterCaseTelephonyService
{
    private DerivedTelephonyStateFactory $derivedTelephonyStateFactory;
    private ServiceNumberLinkRepository $serviceNumberLinkRepository;
    private TelephonyService $telephonyService;
    private CallService $callService;
    private TransactionHandler $transactionHandler;

    public function __construct(
        DerivedTelephonyStateFactory $derivedTelephonyStateFactory,
        ServiceNumberLinkRepository $serviceNumberLinkRepository,
        TelephonyService $telephonyService,
        CallService $callService,
        TransactionHandler $transactionHandler
    ) {
        $this->derivedTelephonyStateFactory = $derivedTelephonyStateFactory;
        $this->serviceNumberLinkRepository = $serviceNumberLinkRepository;
        $this->telephonyService = $telephonyService;
        $this->callService = $callService;
        $this->transactionHandler = $transactionHandler;
    }

    public function callCompany(ServiceCenterCase $case, ?Company $company, PhoneNumber $phoneNumber): void
    {
        $agent = $case->getCaseEntry()->getAssignedAgent();

        if (null === $agent) {
            throw new InvalidArgumentException('Case has no assigned agent');
        }

        AgentSessionAssertions::assertAgentHasActiveAgentSession($agent);
        $this->assertAgentCanStartOutboundCallToCompany($agent);

        $this->telephonyService->callCompany(
            $agent->getActiveAgentSession()->getAgentSessionLogEntry()->getTelephonySession(),
            (
                $this->getCaseSpecificOutboundCallSourceNumber($case) ??
                $this->getDefaultOutboundCompanyCallSourceNumber()
            ),
            $phoneNumber,
            $company
        );
    }

    public function callCustomer(ServiceCenterCase $case, PhoneNumber $customerPhoneNumber): void
    {
        $agent = $case->getCaseEntry()->getAssignedAgent();

        if (null === $agent) {
            throw new InvalidArgumentException('Case has no assigned agent');
        }

        AgentSessionAssertions::assertAgentHasActiveAgentSession($agent);
        $this->assertAgentCanStartOutboundCallToCustomer($agent);

        $telephonySession = $agent->getActiveAgentSession()->getAgentSessionLogEntry()->getTelephonySession();

        $this->transactionHandler->transactional(
            function () use ($case, $telephonySession, $customerPhoneNumber) {
                // Calling customer might result in forwarding call to company
                if (0 === count($telephonySession->getCalls())) {
                    $this->callService->createCallFromTelephonySession(
                        $telephonySession,
                        $customerPhoneNumber
                    );
                }

                $this->telephonyService->callCustomer(
                    $telephonySession,
                    (
                        $this->getCaseSpecificOutboundCallSourceNumber($case) ??
                        $this->getDefaultOutboundCustomerCallSourceNumber()
                    ),
                    $customerPhoneNumber
                );
            }
        );
    }

    private function getCaseSpecificOutboundCallSourceNumber(ServiceCenterCase $case): ?PhoneNumber
    {
        $calledNumberInfo = $case->getCalledNumberInfo();

        if ($calledNumberInfo === null || $calledNumberInfo->isEffectiveMatchmakerType()) {
            return null;
        }

        return $case->getServicenumberLink()->getServicenumber()->getPhoneNumber();
    }

    private function getDefaultOutboundCustomerCallSourceNumber(): PhoneNumber
    {
        return $this->serviceNumberLinkRepository->query()
            ->whereSystemType()
            ->whereLabel(ServicenumberLink::SYSTEM_LABEL_SC_OUTBOUND_CALL_SOURCE_CUSTOMER)
            ->firstOrFail()
            ->getServicenumber()
            ->getPhoneNumber();
    }

    private function getDefaultOutboundCompanyCallSourceNumber(): PhoneNumber
    {
        return $this->serviceNumberLinkRepository->query()
            ->whereSystemType()
            ->whereLabel(ServicenumberLink::SYSTEM_LABEL_SC_OUTBOUND_CALL_SOURCE_COMPANY)
            ->firstOrFail()
            ->getServicenumber()
            ->getPhoneNumber();
    }

    public function getCompanyPhoneNumber(Company $company): PhoneNumber
    {
        if (null !== $company->getActiveMatchmakerSubscription()) {
            $phoneNumber = $company->getActiveMatchmakerSubscription()->getCurrentPhonenumber();

            if (null !== $phoneNumber) {
                return $phoneNumber;
            }
        }

        if (null !== $company->getActivePplSubscription()) {
            $phoneNumber = $company->getActivePplSubscription()->getCurrentPhonenumber();

            if (null !== $phoneNumber) {
                return $phoneNumber;
            }
        }

        if (null !== $company->getActiveDedicatedSubscription()) {
            $phoneNumber = $company->getActiveDedicatedSubscription()->getCurrentPhonenumber();

            if (null !== $phoneNumber) {
                return $phoneNumber;
            }
        }

        throw new UnexpectedValueException('Company does not have an active phone number');
    }

    private function assertAgentCanStartOutboundCallToCompany(User $agent): void
    {
        AgentSessionAssertions::assertAgentSessionHasTelephonySession($agent->getActiveAgentSession());

        $telephonySession = $agent->getActiveAgentSession()->getAgentSessionLogEntry()->getTelephonySession();
        $telephonyState = $this->derivedTelephonyStateFactory->createFromTelephonySession($telephonySession);

        if ($telephonyState->hasChannelByReference(ChannelReferences::COMPANY)) {
            throw new UnexpectedValueException('Agent already has an outbound channel to a company');
        }
    }

    private function assertAgentCanStartOutboundCallToCustomer(User $agent): void
    {
        AgentSessionAssertions::assertAgentSessionHasTelephonySession($agent->getActiveAgentSession());

        $telephonySession = $agent->getActiveAgentSession()->getAgentSessionLogEntry()->getTelephonySession();
        $telephonyState = $this->derivedTelephonyStateFactory->createFromTelephonySession($telephonySession);

        // Any non-company channel is regarded as the customer channel
        $hasCustomerChannel = Collection::make($telephonyState->getChannels())
            ->contains(static function (DerivedChannelState $channel): bool {
                return $channel->getReference() !== ChannelReferences::COMPANY;
            });

        if ($hasCustomerChannel) {
            throw new UnexpectedValueException('Agent already has a channel to a customer');
        }
    }
}
