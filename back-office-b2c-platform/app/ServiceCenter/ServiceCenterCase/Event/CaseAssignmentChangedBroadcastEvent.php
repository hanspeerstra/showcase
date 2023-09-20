<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Event;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Neves\Events\Contracts\TransactionalEvent;

class CaseAssignmentChangedBroadcastEvent implements ShouldBroadcast, TransactionalEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastAs(): string
    {
        return 'sc.case.assignment.changed';
    }

    /**
     * @inheritdoc
     */
    public function broadcastOn()
    {
        return new Channel('sc.case');
    }
}
