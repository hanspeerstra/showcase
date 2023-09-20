<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\Auth\User;
use App\Leads\Factory\LeadFactory;
use App\Leads\LeadRepository;
use App\Models\Office\Company;
use App\Models\Office\Site;
use App\ServiceCenter\AgentSession\Assertion\AgentSessionAssertions;
use App\ServiceCenter\ServiceCenterCase\Assertion\ServiceCenterCaseAssertions;
use App\ServiceCenter\ServiceCenterCase\Http\Request\ForwardCallToCompanyRequest;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\Telephony\ChannelReferences;
use App\ServiceCenter\Telephony\DerivedChannelState;
use App\ServiceCenter\Telephony\Factory\DerivedTelephonyStateFactory;
use App\Telephony\Call\CallService;
use App\Telephony\Commands\TelephonyCommandFactory;
use App\Telephony\Session\TelephonySessionService;
use App\Utils\Database\Contract\TransactionHandler;
use Illuminate\Support\Collection;
use Propaganistas\LaravelPhone\PhoneNumber;
use UnexpectedValueException;

class ForwardCallToCompanyAction
{
    private TransactionHandler $transactionHandler;
    private LeadFactory $leadFactory;
    private CallService $callService;
    private DerivedTelephonyStateFactory $derivedTelephonyStateFactory;
    private LeadRepository $leadRepository;
    private TelephonyCommandFactory $telephonyCommandFactory;
    private TelephonySessionService $telephonySessionService;
    private ServiceCenterCaseService $caseService;

    public function __construct(
        TransactionHandler $transactionHandler,
        LeadFactory $leadFactory,
        CallService $callService,
        LeadRepository $leadRepository,
        DerivedTelephonyStateFactory $derivedTelephonyStateFactory,
        TelephonyCommandFactory $telephonyCommandFactory,
        TelephonySessionService $telephonySessionService,
        ServiceCenterCaseService $caseService
    ) {
        $this->transactionHandler = $transactionHandler;
        $this->leadFactory = $leadFactory;
        $this->callService = $callService;
        $this->leadRepository = $leadRepository;
        $this->derivedTelephonyStateFactory = $derivedTelephonyStateFactory;
        $this->telephonyCommandFactory = $telephonyCommandFactory;
        $this->telephonySessionService = $telephonySessionService;
        $this->caseService = $caseService;
    }

    public function handle(ForwardCallToCompanyRequest $request): ServiceCenterCase
    {
        return $this->transactionHandler->transactional(function () use ($request) {
            AgentSessionAssertions::assertAgentHasActiveAgentSession($request->getAgent());
            self::assertAgentIsAssignedToCase($request->getAgent(), $request->getCase());
            $this->assertAgentCanForwardCall($request->getAgent());

            $agentSession = $request->getAgent()->getActiveAgentSession();
            $telephonySession = $agentSession->getAgentSessionLogEntry()->getTelephonySession();
            $derivedTelephonyState = $this->derivedTelephonyStateFactory->createFromTelephonySession(
                $telephonySession
            );

            $companyChannel = $derivedTelephonyState->getChannelByReference(ChannelReferences::COMPANY);
            /** @var DerivedChannelState $customerChannel */
            $customerChannel = Collection::make($derivedTelephonyState->getChannels())
                ->first(static function (DerivedChannelState $channel) use ($companyChannel) {
                    return $channel->getChannelId() !== $companyChannel->getChannelId();
                });

            // TODO remove or use company_id in channel metadata
            $this->assertPhoneNumberBelongsToCompany(
                $request->getSubscription()->getCompany(),
                $companyChannel->getRemotePhoneNumber()
            );

            $lead = $this->leadFactory->createForCallForwardedByServiceCenter(
                $request->getSubscription(),
                $request->getRegion(),
                $request->getServiceType(),
                $request->getSite() ?? Site::getDefaultSite(),
                $request->getSource(),
                $request->getGclid(),
                $derivedTelephonyState->getMatchedServiceNumberLink(),
                $request->isNoCharge(),
                $request->getCase()->getLeadSourceInfo()
            );
            $lead = $this->leadRepository->insert($lead);

            $this->callService->updateTelephonySessionCallOnForwarded(
                $telephonySession,
                $companyChannel->getLocalPhoneNumber(),
                $companyChannel->getRemotePhoneNumber(),
                $lead
            );

            $forwardCallCommand = $this->telephonyCommandFactory->forwardCallToOutboundChannel(
                $customerChannel->getChannelId(),
                $companyChannel->getChannelId()
            );
            $this->telephonySessionService->dispatchCommand($telephonySession, $forwardCallCommand);

            return $this->caseService->setCaseResult(
                $request->getCase(),
                $lead,
                null,
                null
            );
        });
    }

    private function assertPhoneNumberBelongsToCompany(Company $company, PhoneNumber $phoneNumber): void
    {
        if (null !== $company->getActiveMatchmakerSubscription()) {
            $currentPhoneNumber = $company->getActiveMatchmakerSubscription()->getCurrentPhonenumber();

            if (null !== $currentPhoneNumber && $currentPhoneNumber->formatE164() === $phoneNumber->formatE164()) {
                return;
            }
        }

        $phoneNumbers = $company->getPhoneNumbers();

        foreach ($phoneNumbers as $companyPhoneNumber) {
            if ($companyPhoneNumber->formatE164() === $phoneNumber->formatE164()) {
                return;
            }
        }

        throw new UnexpectedValueException(sprintf(
            'Phone number "%s" does not belong to company %d',
            $phoneNumber->formatE164(),
            $company->getId()
        ));
    }

    private static function assertAgentIsAssignedToCase(User $agent, ServiceCenterCase $case): void
    {
        ServiceCenterCaseAssertions::assertThatCaseBelongsToAgentSession(
            $case,
            $agent->getActiveAgentSession()
        );
    }

    private function assertAgentCanForwardCall(User $agent): void
    {
        AgentSessionAssertions::assertAgentSessionHasTelephonySession($agent->getActiveAgentSession());

        $telephonySession = $agent->getActiveAgentSession()->getAgentSessionLogEntry()->getTelephonySession();
        $telephonyState = $this->derivedTelephonyStateFactory->createFromTelephonySession($telephonySession);

        if (!$telephonyState->hasChannelByReference(ChannelReferences::COMPANY)) {
            throw new UnexpectedValueException('Agent does not have an outbound channel to a company');
        }

        if (2 !== $telephonyState->getActiveChannelCount()) {
            throw new UnexpectedValueException(
                'Agent can only start forward call if there are two other channels active. Number of active channels: ' . $telephonyState->getActiveChannelCount()
            );
        }
    }
}
