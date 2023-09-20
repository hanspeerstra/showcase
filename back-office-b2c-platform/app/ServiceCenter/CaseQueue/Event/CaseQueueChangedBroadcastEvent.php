<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseQueue\Event;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Neves\Events\Contracts\TransactionalEvent;

class CaseQueueChangedBroadcastEvent implements ShouldBroadcast, TransactionalEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastAs(): string
    {
        return 'sc.queue.changed';
    }

    /**
     * @inheritdoc
     */
    public function broadcastOn()
    {
        return new Channel('sc.queue');
    }
}
