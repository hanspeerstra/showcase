<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Assertion;

use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Telephony\Session\StateMachineFactory;
use App\Telephony\Session\Util\TelephonySessionStateMachineUtil;
use UnexpectedValueException;

class CanAgentUnassignFromCase
{
    private StateMachineFactory $stateMachineFactory;
    private TelephonySessionStateMachineUtil $telephonySessionStateMachineUtil;

    public function __construct(
        StateMachineFactory $stateMachineFactory,
        TelephonySessionStateMachineUtil $telephonySessionStateMachineUtil
    ) {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->telephonySessionStateMachineUtil = $telephonySessionStateMachineUtil;
    }

    public function assert(ServiceCenterCase $case, AgentSession $agentSession): void
    {
        if ($case->isPaused()) {
            ServiceCenterCaseAssertions::assertThatCaseIsAssignedToAgent($case, $agentSession);
        } else {
            ServiceCenterCaseAssertions::assertThatCaseBelongsToAgentSession($case, $agentSession);
        }

        $telephonySession = $case->getSourceTelephonySession();
        if ($telephonySession !== null) {
            $telephonySessionStateMachine = $this->stateMachineFactory->fromSession($telephonySession);

            $hasBeenAnsweredByAgent = $this->telephonySessionStateMachineUtil->hasBeenAnsweredByAgent(
                $telephonySessionStateMachine
            );
            $hasAgentAnActiveChannel = $this->telephonySessionStateMachineUtil->hasAgentAnActiveChannel(
                $telephonySessionStateMachine,
                $agentSession
            );
            $hasResult = $case->hasResult();
            $isCaseClosed = $case->isClosed();
            $hasActiveTelephonySession = $telephonySession->isActive() || $agentSession->hasActiveTelephonySession();

            if (true !== self::canAgentUnassignFromCaseLookup($hasBeenAnsweredByAgent, $hasAgentAnActiveChannel, $hasResult, $isCaseClosed, $hasActiveTelephonySession)) {
                throw new UnexpectedValueException(
                    self::cannotUnassignReason(
                        $case,
                        $agentSession,
                        $hasBeenAnsweredByAgent,
                        $hasAgentAnActiveChannel,
                        $hasResult,
                        $isCaseClosed,
                        $hasActiveTelephonySession
                    )
                );
            }
        }
    }

    private static function canAgentUnassignFromCaseLookup(
        bool $hasBeenAnsweredByAgent,
        bool $hasAgentAnActiveChannel,
        bool $hasCaseResult,
        bool $isCaseClosed,
        bool $hasActiveTelephonySession
    ) {
        $lookupTable[1][0][0][0][0] = true;
        $lookupTable[1][0][0][0][1] = 'Case result is missing';
        $lookupTable[1][1][0][0][0] = 'Cannot unassign when there are active agent channel(s)';
        $lookupTable[1][1][0][0][1] = 'Cannot unassign when there are active agent channel(s)';
        $lookupTable[1][0][1][0][0] = 'Must close case';
        $lookupTable[1][0][1][0][1] = 'Must close case';
        $lookupTable[1][0][0][1][0] = 'Case result is missing';
        $lookupTable[1][0][0][1][1] = 'Case result is missing';
        $lookupTable[1][0][1][1][0] = true;
        $lookupTable[1][0][1][1][1] = true;
        $lookupTable[1][1][1][0][0] = 'Cannot unassign when there are active agent channel(s)';
        $lookupTable[1][1][1][0][1] = 'Cannot unassign when there are active agent channel(s)';
        $lookupTable[1][1][0][1][0] = 'Cannot unassign when there are active agent channel(s)';
        $lookupTable[1][1][0][1][1] = 'Cannot unassign when there are active agent channel(s)';
        $lookupTable[1][1][1][1][0] = 'SC is calling the agent, cannot unassign when there are active agent channel(s)';
        $lookupTable[1][1][1][1][1] = 'SC is calling the agent, cannot unassign when there are active agent channel(s)';
        $lookupTable[0][0][0][0][0] = true;
        $lookupTable[0][0][0][0][1] = true;
        $lookupTable[0][1][0][0][0] = 'SC is calling the agent, cannot unassign when there are active agent channel(s)';
        $lookupTable[0][1][0][0][1] = 'SC is calling the agent, cannot unassign when there are active agent channel(s)';
        $lookupTable[0][0][1][0][0] = 'Must close case';
        $lookupTable[0][0][1][0][1] = 'Must close case';
        $lookupTable[0][0][0][1][0] = 'Case result is missing';
        $lookupTable[0][0][0][1][1] = 'Case result is missing';
        $lookupTable[0][0][1][1][0] = true;
        $lookupTable[0][0][1][1][1] = true;
        $lookupTable[0][1][1][0][0] = 'SC is calling the agent, cannot unassign when there are active agent channel(s)';
        $lookupTable[0][1][1][0][1] = 'SC is calling the agent, cannot unassign when there are active agent channel(s)';
        $lookupTable[0][1][1][1][0] = 'SC is calling the agent, cannot unassign when there are active agent channel(s)';
        $lookupTable[0][1][1][1][1] = 'SC is calling the agent, cannot unassign when there are active agent channel(s)';
        $lookupTable[0][1][0][1][0] = 'SC is calling the agent, cannot unassign when there are active agent channel(s)';
        $lookupTable[0][1][0][1][1] = 'SC is calling the agent, cannot unassign when there are active agent channel(s)';

        return $lookupTable[(int) $hasBeenAnsweredByAgent][(int) $hasAgentAnActiveChannel][(int) $hasCaseResult][(int) $isCaseClosed][(int) $hasActiveTelephonySession];
    }

    private static function cannotUnassignReason(
        ServiceCenterCase $case,
        AgentSession $agentSession,
        bool $hasBeenAnsweredByAgent,
        bool $hasAgentAnActiveChannel,
        bool $hasCaseResult,
        bool $isCaseClosed,
        bool $hasActiveTelephonySession
    ): string {
        return sprintf(
            '%s - %s',
            self::canAgentUnassignFromCaseLookup($hasBeenAnsweredByAgent, $hasAgentAnActiveChannel, $hasCaseResult, $isCaseClosed, $hasActiveTelephonySession),
            self::getSuffixErrorMessage($case, $agentSession, $hasBeenAnsweredByAgent, $hasAgentAnActiveChannel, $hasCaseResult, $isCaseClosed, $hasActiveTelephonySession)
        );
    }

    private static function getSuffixErrorMessage(
        ServiceCenterCase $case,
        AgentSession $agentSession,
        bool $hasBeenAnsweredByAgent,
        bool $hasAgentAnActiveChannel,
        bool $hasCaseResult,
        bool $isCaseClosed,
        bool $hasActiveTelephonySession
    ): string {
        return sprintf(
            'Case (id: %s) AgentSession (ID: %s) $hasBeenAnsweredByAgent (%b) $hasAgentAnActiveChannel (%b) $hasCaseResult (%b) $isCaseClosed (%b) $hasActiveTelephonySession (%b)',
            $case->getId(),
            $agentSession->getId(),
            $hasBeenAnsweredByAgent,
            $hasAgentAnActiveChannel,
            $hasCaseResult,
            $isCaseClosed,
            $hasActiveTelephonySession
        );
    }
}
