<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\Auth\User;
use App\ServiceCenter\AgentSession\Assertion\AgentSessionAssertions;
use App\ServiceCenter\ServiceCenterCase\Assertion\ServiceCenterCaseAssertions;
use App\ServiceCenter\ServiceCenterCase\Http\Request\ForwardCallToCompanyHangupRequest;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\Telephony\ChannelReferences;
use App\ServiceCenter\Telephony\Factory\DerivedTelephonyStateFactory;
use App\Telephony\Commands\TelephonyCommandFactory;
use App\Telephony\Session\Model\TelephonySession;
use App\Telephony\Session\TelephonySessionService;
use App\Utils\Database\Contract\TransactionHandler;
use UnexpectedValueException;

class ForwardCallToCompanyHangupAction
{
    private TransactionHandler $transactionHandler;
    private DerivedTelephonyStateFactory $derivedTelephonyStateFactory;
    private TelephonyCommandFactory $telephonyCommandFactory;
    private TelephonySessionService $telephonySessionService;

    public function __construct(
        TransactionHandler $transactionHandler,
        DerivedTelephonyStateFactory $derivedTelephonyStateFactory,
        TelephonyCommandFactory $telephonyCommandFactory,
        TelephonySessionService $telephonySessionService
    ) {
        $this->transactionHandler = $transactionHandler;
        $this->derivedTelephonyStateFactory = $derivedTelephonyStateFactory;
        $this->telephonyCommandFactory = $telephonyCommandFactory;
        $this->telephonySessionService = $telephonySessionService;
    }

    public function handle(ForwardCallToCompanyHangupRequest $request): void
    {
        $this->transactionHandler->transactional(function () use ($request) {
            $agent = $request->getAgent();

            AgentSessionAssertions::assertAgentHasActiveAgentSession($agent);
            $this->assertAgentIsAssignedToCase($agent, $request->getCase());
            $this->assertAgentCanHangupCompanyChannel($agent);

            $telephonySession = $this->getTelephonySession($agent);
            $derivedTelephonyState = $this->derivedTelephonyStateFactory->createFromTelephonySession(
                $telephonySession
            );

            $companyChannelId = $derivedTelephonyState->getChannelByReference(ChannelReferences::COMPANY)->getChannelId();
            $hangupCommand = $this->telephonyCommandFactory->hangupChannel($companyChannelId);
            $this->telephonySessionService->dispatchCommand($telephonySession, $hangupCommand);
        });
    }

    private function assertAgentIsAssignedToCase(User $agent, ServiceCenterCase $case): void
    {
        ServiceCenterCaseAssertions::assertThatCaseBelongsToAgentSession(
            $case,
            $agent->getActiveAgentSession()
        );
    }

    private function assertAgentCanHangupCompanyChannel(User $agent): void
    {
        AgentSessionAssertions::assertAgentSessionHasTelephonySession($agent->getActiveAgentSession());

        $telephonySession = $this->getTelephonySession($agent);
        $telephonyState = $this->derivedTelephonyStateFactory->createFromTelephonySession($telephonySession);

        if (!$telephonyState->hasChannelByReference(ChannelReferences::COMPANY)) {
            throw new UnexpectedValueException('Agent does not have an outbound channel to a company');
        }
    }

    private function getTelephonySession(User $agent): TelephonySession
    {
        return $agent->getActiveAgentSession()->getAgentSessionLogEntry()->getTelephonySession();
    }
}
