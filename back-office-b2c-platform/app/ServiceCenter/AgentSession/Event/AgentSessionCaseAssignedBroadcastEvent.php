<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Event;

use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Neves\Events\Contracts\TransactionalEvent;

class AgentSessionCaseAssignedBroadcastEvent implements ShouldBroadcast, TransactionalEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var ServiceCenterCase */
    private $agentSession;

    /** @var ServiceCenterCase */
    private $case;

    public function __construct(AgentSession $agentSession, ServiceCenterCase $case)
    {
        $this->agentSession = $agentSession;
        $this->case = $case;
    }

    public function broadcastWith(): array
    {
        return [
            'caseId' => $this->case->getId(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sc.agentSession.caseAssigned';
    }

    /**
     * @inheritdoc
     */
    public function broadcastOn()
    {
        return new Channel("sc.agentSession.{$this->agentSession->getId()}");
    }
}
