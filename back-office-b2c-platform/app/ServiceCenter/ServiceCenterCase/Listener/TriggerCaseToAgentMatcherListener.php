<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Listener;

use App\ServiceCenter\AgentSession\Event\AgentSessionStartedEvent;
use App\ServiceCenter\CaseQueue\Event\CaseOnQueueEvent;
use App\ServiceCenter\CaseQueue\Job\ProcessCaseQueueJob;
use App\ServiceCenter\ServiceCenterCase\Event\CaseClosedEvent;
use App\ServiceCenter\ServiceCenterCase\Event\CaseWasUnassignedEvent;
use Illuminate\Contracts\Bus\Dispatcher;

class TriggerCaseToAgentMatcherListener
{
    /** @var Dispatcher */
    private $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function onCaseUnassigned(CaseWasUnassignedEvent $event): void
    {
        $this->dispatchProcessCaseQueueJob();
    }

    public function onCaseClosed(CaseClosedEvent $event): void
    {
        $this->dispatchProcessCaseQueueJob();
    }

    public function onQueue(CaseOnQueueEvent $event): void
    {
        $this->dispatchProcessCaseQueueJob();
    }

    public function onAgentSessionStarted(AgentSessionStartedEvent $event): void
    {
        $this->dispatchProcessCaseQueueJob();
    }

    private function dispatchProcessCaseQueueJob(): void
    {
        $this->dispatcher->dispatch(new ProcessCaseQueueJob());
    }
}
