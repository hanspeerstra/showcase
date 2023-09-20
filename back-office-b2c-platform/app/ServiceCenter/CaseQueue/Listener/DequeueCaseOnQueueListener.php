<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseQueue\Listener;

use App\ServiceCenter\CaseQueue\Service\CaseQueueService;
use App\ServiceCenter\ServiceCenterCase\Event\CaseClosedEvent;

class DequeueCaseOnQueueListener
{
    /** @var CaseQueueService */
    private $caseQueueService;

    public function __construct(CaseQueueService $caseQueueService)
    {
        $this->caseQueueService = $caseQueueService;
    }

    public function onCaseClosed(CaseClosedEvent $event): void
    {
        $this->caseQueueService->dequeueByCase($event->getCase());
    }
}
