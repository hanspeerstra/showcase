<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Service;

use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\ServiceCenterCase\Http\Request\StartNewTelephonySessionRequest;
use App\ServiceCenter\Telephony\Service\TelephonyService;
use App\Utils\Database\Contract\TransactionHandler;

class StartNewTelephonySessionAction
{
    /** @var TelephonyService */
    private $telephonyService;
    /** @var AgentSessionService */
    private $agentSessionService;
    /** @var ServiceCenterCaseService */
    private $caseService;
    /** @var TransactionHandler */
    private $transactionHandler;

    public function __construct(
        TelephonyService $telephonyService,
        AgentSessionService $agentSessionService,
        ServiceCenterCaseService $caseService,
        TransactionHandler $transactionHandler
    ) {
        $this->telephonyService = $telephonyService;
        $this->agentSessionService = $agentSessionService;
        $this->caseService = $caseService;
        $this->transactionHandler = $transactionHandler;
    }

    public function handle(StartNewTelephonySessionRequest $request): void
    {
        $case = $request->getCase();
        $agentSession = $request->getAgent()->getActiveAgentSession();
        $agentPhoneNumber = $agentSession->getInternalPhone()->getExternalPhoneNumber();

        $this->transactionHandler->transactional(
            function () use ($agentSession, $case, $agentPhoneNumber): void {
                $telephonySession = $this->telephonyService->createOutboundTelephonySession();

                $this->agentSessionService->attachTelephonySession($agentSession, $telephonySession);
                $this->caseService->attachTelephonySession($case, $telephonySession);

                $this->telephonyService->startOutboundTelephonySession(
                    $telephonySession,
                    $agentPhoneNumber,
                    $agentSession
                );
            }
        );
    }
}
