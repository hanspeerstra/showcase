<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Event;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Telephony\Session\Model\TelephonySession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Neves\Events\Contracts\TransactionalEvent;

class CaseChangedBroadcastEvent implements ShouldBroadcast, TransactionalEvent
{
    use Dispatchable, InteractsWithSockets;

    /** @var ServiceCenterCase */
    private $case;

    /** @var TelephonySession */
    private $telephonySession;

    public function __construct(ServiceCenterCase $case)
    {
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
        return 'sc.case.changed';
    }

    /**
     * @inheritdoc
     */
    public function broadcastOn()
    {
        return new Channel("sc.case.{$this->case->getId()}");
    }
}
