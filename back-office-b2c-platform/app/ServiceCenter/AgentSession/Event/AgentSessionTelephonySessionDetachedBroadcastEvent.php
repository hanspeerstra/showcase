<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Event;

use App\ServiceCenter\AgentSession\AgentSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Neves\Events\Contracts\TransactionalEvent;

class AgentSessionTelephonySessionDetachedBroadcastEvent implements ShouldBroadcast, TransactionalEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var AgentSession */
    private $agentSession;

    public function __construct(AgentSession $agentSession)
    {
        $this->agentSession = $agentSession;
    }

    public function broadcastWith(): array
    {
        return [
            'agentSessionId' => $this->agentSession->getId(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sc.agentSession.telephonySessionDetached';
    }

    /**
     * @inheritdoc
     */
    public function broadcastOn(): Channel
    {
        return new Channel("sc.agentSession.{$this->agentSession->getId()}");
    }
}
