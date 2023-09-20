<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Event;

use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Neves\Events\Contracts\TransactionalEvent;

class CaseAgentAssigned implements TransactionalEvent
{
    /** @var ServiceCenterCase */
    private $case;
    /** @var AgentSession */
    private $agentSession;

    public function __construct(ServiceCenterCase $case, AgentSession $agentSession)
    {
        $this->case = $case;
        $this->agentSession = $agentSession;
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    public function getAgentSession(): AgentSession
    {
        return $this->agentSession;
    }
}
