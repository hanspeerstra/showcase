<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession;

use Assert\Assert;

class AgentSessionLifetimeConfig
{
    /** @var int */
    private $agentSessionLifetimeInMinutes;

    public function __construct(int $agentSessionLifetimeInMinutes)
    {
        Assert::that($agentSessionLifetimeInMinutes)->greaterThan(0);

        $this->agentSessionLifetimeInMinutes = $agentSessionLifetimeInMinutes;
    }

    public function getAgentSessionLifetimeInMinutes(): int
    {
        return $this->agentSessionLifetimeInMinutes;
    }
}
