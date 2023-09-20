<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Exception;

use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Exception;

class CannotEndAgentSessionException extends Exception
{
    /**
     * @param ServiceCenterCase[] $assignedCases
     */
    public static function hasCasesAssigned(AgentSession $agentSession, iterable $assignedCases): self
    {
        $assignedCasesIdList = collect($assignedCases)
            ->map(static function (ServiceCenterCase $case): int {
                return $case->getId();
            })
            ->all();

        return new static(
            sprintf(
                'Cannot end agent session when there are case(s) assigned to agent (agentSessionId=%d, assignedCases=[%s])',
                $agentSession->getId(),
                implode(',', $assignedCasesIdList)
            ));
    }
}
