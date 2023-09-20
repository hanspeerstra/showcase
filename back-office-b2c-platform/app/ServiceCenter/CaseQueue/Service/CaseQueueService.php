<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseQueue\Service;

use App\ServiceCenter\CaseQueue\CaseQueueEntry;
use App\ServiceCenter\CaseQueue\Event\CaseOnQueueEvent;
use App\ServiceCenter\CaseQueue\Event\CaseQueueChangedBroadcastEvent;
use App\ServiceCenter\CaseQueue\Repository\CaseQueueRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Utils\Database\Contract\TransactionHandler;
use Illuminate\Contracts\Events\Dispatcher;

class CaseQueueService
{
    private CaseQueueRepository $caseQueueRepository;
    private Dispatcher $dispatcher;
    private TransactionHandler $transactionHandler;

    public function __construct(
        CaseQueueRepository $caseQueueRepository,
        TransactionHandler $transactionHandler,
        Dispatcher $dispatcher
    ) {
        $this->caseQueueRepository = $caseQueueRepository;
        $this->transactionHandler = $transactionHandler;
        $this->dispatcher = $dispatcher;
    }

    public function enqueue(ServiceCenterCase $case): CaseQueueEntry
    {
        $automaticallyAssign = true;
        if (!$case->isInteractiveCase() && $this->caseQueueRepository->findInitialQueueEntryByCase($case) !== null) {
            // Re-queued passive cases should not be automatically assigned to an agent
            $automaticallyAssign = false;
        }

        $caseQueueEntry = CaseQueueEntry::makeInstance(
            $case,
            $case->getCaseEntry()->getWorkGroup(),
            $automaticallyAssign
        );

        $this->transactionHandler->doInLockedTransaction($case, function () use ($caseQueueEntry) {
            $this->caseQueueRepository->insert($caseQueueEntry);
        });

        $this->dispatchCaseOnQueueEvent();

        return $caseQueueEntry;
    }

    public function dequeueByCase(ServiceCenterCase $case): void
    {
        $caseQueueEntry = $this->caseQueueRepository->getByCase($case);

        if ($caseQueueEntry !== null) {
            $this->transactionHandler->doInLockedTransaction($case, function () use ($caseQueueEntry) {
                $this->caseQueueRepository->delete($caseQueueEntry);
            });

            $this->dispatcher->dispatch(new CaseQueueChangedBroadcastEvent());
        }
    }

    public function isQueued(ServiceCenterCase $case): bool
    {
        return null !== $this->caseQueueRepository->getByCase($case);
    }

    private function dispatchCaseOnQueueEvent(): void
    {
        $this->dispatcher->dispatch(new CaseOnQueueEvent());
        $this->dispatcher->dispatch(new CaseQueueChangedBroadcastEvent());
    }
}
