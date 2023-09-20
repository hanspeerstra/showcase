<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Assertion;

use App\Auth\User;
use App\ServiceCenter\AgentSession\AgentSession;
use UnexpectedValueException;

class AgentSessionAssertions
{
    public static function assertAgentHasActiveAgentSession(User $agent): void
    {
        if (null === $agent->getActiveAgentSession()) {
            throw new UnexpectedValueException('Agent does not have an active agent session');
        }
    }

    public static function assertAgentSessionHasTelephonySession(AgentSession $agentSession): void
    {
        $telephonySession = $agentSession->getAgentSessionLogEntry()->getTelephonySession();

        if (null === $telephonySession) {
            throw new UnexpectedValueException('Agent does not have a telephony session');
        }
    }
}
