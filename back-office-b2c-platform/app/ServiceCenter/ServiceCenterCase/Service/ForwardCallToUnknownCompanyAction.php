<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\Auth\User;
use App\ServiceCenter\AgentSession\Assertion\AgentSessionAssertions;
use App\ServiceCenter\ServiceCenterCase\Assertion\ServiceCenterCaseAssertions;
use App\ServiceCenter\ServiceCenterCase\Http\Request\ForwardCallToUnknownCompanyRequest;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseNote;
use App\ServiceCenter\Telephony\ChannelReferences;
use App\ServiceCenter\Telephony\DerivedChannelState;
use App\ServiceCenter\Telephony\Factory\DerivedTelephonyStateFactory;
use App\Telephony\Call\CallService;
use App\Telephony\Commands\TelephonyCommandFactory;
use App\Telephony\Session\TelephonySessionService;
use App\Utils\Database\Contract\TransactionHandler;
use Illuminate\Support\Collection;
use UnexpectedValueException;

class ForwardCallToUnknownCompanyAction
{
    private TransactionHandler $transactionHandler;
    private CallService $callService;
    private DerivedTelephonyStateFactory $derivedTelephonyStateFactory;
    private TelephonyCommandFactory $telephonyCommandFactory;
    private TelephonySessionService $telephonySessionService;
    private ServiceCenterCaseService $caseService;

    public function __construct(
        TransactionHandler $transactionHandler,
        CallService $callService,
        DerivedTelephonyStateFactory $derivedTelephonyStateFactory,
        TelephonyCommandFactory $telephonyCommandFactory,
        TelephonySessionService $telephonySessionService,
        ServiceCenterCaseService $caseService
    ) {
        $this->transactionHandler = $transactionHandler;
        $this->callService = $callService;
        $this->derivedTelephonyStateFactory = $derivedTelephonyStateFactory;
        $this->telephonyCommandFactory = $telephonyCommandFactory;
        $this->telephonySessionService = $telephonySessionService;
        $this->caseService = $caseService;
    }

    public function handle(ForwardCallToUnknownCompanyRequest $request): ServiceCenterCase
    {
        return $this->transactionHandler->transactional(function () use ($request) {
            AgentSessionAssertions::assertAgentHasActiveAgentSession($request->getAgent());
            $this->assertAgentIsAssignedToCase($request->getAgent(), $request->getCase());
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

            $this->callService->updateTelephonySessionCallOnForwarded(
                $telephonySession,
                $companyChannel->getLocalPhoneNumber(),
                $companyChannel->getRemotePhoneNumber(),
                null
            );

            $forwardCallCommand = $this->telephonyCommandFactory->forwardCallToOutboundChannel(
                $customerChannel->getChannelId(),
                $companyChannel->getChannelId()
            );
            $this->telephonySessionService->dispatchCommand($telephonySession, $forwardCallCommand);

            $this->caseService->addCaseNote(
                ServiceCenterCaseNote::makeInstance(
                    $request->getCase(),
                    $request->getAgent(),
                    sprintf(
                        'Beller doorverbonden met bedrijf die nog geen klant is. Beller: %s, bedrijf: %s, Werkzaamheid: %s, Regio: %s',
                        $customerChannel->getRemotePhoneNumber() ? $customerChannel->getRemotePhoneNumber()->formatE164() : 'onbekend',
                        $companyChannel->getRemotePhoneNumber() ? $customerChannel->getRemotePhoneNumber()->formatE164() : 'onbekend',
                        $request->getServiceType() ? $request->getServiceType()->getName() : 'geen',
                        $request->getRegion() ? $request->getRegion()->getName() : 'geen'
                    )
                )
            );

            return $request->getCase();
        });
    }

    private function assertAgentIsAssignedToCase(User $agent, ServiceCenterCase $case): void
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
