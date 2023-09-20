<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Assertion;

use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use UnexpectedValueException;

class ServiceCenterCaseAssertions
{
    public static function assertThatCaseBelongsToAgentSession(
        ServiceCenterCase $case,
        AgentSession $agentSession
    ): void {
        $caseAgentSession = null !== $case->getCurrentAgentSessionLogEntry()
            ? $case->getCurrentAgentSessionLogEntry()->getAgentSession()
            : null;

        if (null === $caseAgentSession || $caseAgentSession->getId() !== $agentSession->getId()) {
            throw new UnexpectedValueException(sprintf(
                'Case (id: %s) does not belong to AgentSession (case-agent-session-id: %s, agent-session-id: %s)',
                $case->getId(),
                null !== $caseAgentSession ? $caseAgentSession->getId() : 'NULL',
                $agentSession->getId()
            ));
        }
    }

    public static function assertThatCaseIsAssignedToAgent(ServiceCenterCase $case, AgentSession $agentSession): void
    {
        $assignedAgent = $case->getCaseEntry()->getAssignedAgent();

        if ($assignedAgent === null) {
            throw new UnexpectedValueException(sprintf(
                'Case (id: %s) does not have an assigned Agent',
                $case->getId()
            ));
        }

        if ($assignedAgent->getId() !== $agentSession->getUser()->getId()) {
            throw new UnexpectedValueException(sprintf(
                'Case (id: %s) does not belong to AgentSession (case-user-id: %s, agent-session-user-id: %s)',
                $case->getId(),
                $assignedAgent->getId(),
                $agentSession->getUser()->getId()
            ));
        }
    }

    public static function assertThatCaseIsUnassigned(ServiceCenterCase $case): void
    {
        $assignedAgent = $case->getCaseEntry()->getAssignedAgent();

        if ($assignedAgent !== null) {
            throw new UnexpectedValueException(sprintf(
                'Case (id: %s) is already assigned to an agent',
                $case->getId()
            ));
        }
    }

    public static function assertThatCaseDoesNotHaveAnActiveTelephonySession(ServiceCenterCase $case): void
    {
        if ($case->hasActiveSourceTelephonySession()) {
            throw new UnexpectedValueException(sprintf(
                'Case (id: %s) has an active TelephonySession',
                $case->getId()
            ));
        }
    }

    public static function assertThatCaseHasAnActiveAgentSession(ServiceCenterCase $case): void
    {
        if ($case->getCurrentAgentSessionLogEntry() === null) {
            throw new UnexpectedValueException(
                sprintf(
                    'Case (id: %s) does not have an active AgentSession',
                    $case->getId()
                )
            );
        }
    }
}
