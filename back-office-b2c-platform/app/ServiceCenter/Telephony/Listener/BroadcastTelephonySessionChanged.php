<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony\Listener;

use App\ServiceCenter\Telephony\Event\TelephonySessionChangedBroadcastEvent;
use App\Telephony\Session\Event\Channel\AudioConnectionChange;
use App\Telephony\Session\Event\Channel\ChannelCreated;
use App\Telephony\Session\Event\Channel\ChannelStateSwitch;
use App\Telephony\Session\StateTransitionedEvent;
use Illuminate\Contracts\Events\Dispatcher;

class BroadcastTelephonySessionChanged
{
    /** @var Dispatcher */
    private $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function handle(StateTransitionedEvent $event): void
    {
        if ($event->getEvent() instanceof ChannelCreated
            || $event->getEvent() instanceof AudioConnectionChange
            || $event->getEvent() instanceof ChannelStateSwitch
        ) {
            $this->dispatcher->dispatch(
                new TelephonySessionChangedBroadcastEvent(
                    $event->getTelephonySession()
                )
            );
        }
    }
}
