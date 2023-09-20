<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony\Event;

use App\Telephony\Session\Model\TelephonySession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Neves\Events\Contracts\TransactionalEvent;

class TelephonySessionChangedBroadcastEvent implements ShouldBroadcast, TransactionalEvent
{
    /** @var TelephonySession */
    private $telephonySession;

    public function __construct(TelephonySession $telephonySession)
    {
        $this->telephonySession = $telephonySession;
    }

    public function broadcastWith(): array
    {
        return [
            'telephonySessionId' => $this->telephonySession->getId(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sc.telephonySession.changed';
    }

    /**
     * @inheritdoc
     */
    public function broadcastOn()
    {
        return new Channel("sc.telephonySession.{$this->telephonySession->getId()}");
    }
}
