<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Repository;

use App\Auth\User;
use App\Models\Office\Lead;
use App\ServiceCenter\ServiceCenterCase\Event\CaseCreatedEvent;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseEntry;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseNote;
use App\Telephony\Session\Model\TelephonySession;
use App\Utils\Database\Contract\TransactionHandler;
use App\Utils\Storage\EloquentRepositoryHelperTrait;
use DB;
use Illuminate\Contracts\Events\Dispatcher;
use RuntimeException;

class ServiceCenterCaseRepository
{
    use EloquentRepositoryHelperTrait;

    /** @var Dispatcher */
    private $dispatcher;

    /** @var TransactionHandler */
    private $transactionHandler;

    public function __construct(
        Dispatcher $dispatcher,
        TransactionHandler $transactionHandler
    ) {
        $this->dispatcher = $dispatcher;
        $this->transactionHandler = $transactionHandler;
    }

    /**
     * @return ServiceCenterCase[]
     */
    public function findPausedCases(): iterable
    {
        $results = ServiceCenterCase::pausedCases()
            ->notClosed()
            ->get();

        return iterator_to_array($results);
    }

    /**
     * @return ServiceCenterCase[]
     */
    public function findCasesByAssignedAgent(User $agent): iterable
    {
        $results = ServiceCenterCase::notClosed()
            ->hasAgentAssigned($agent)
            ->get();

        return iterator_to_array($results);
    }

    /**
     * @return ServiceCenterCase[]
     */
    public function findAssignedCases(): iterable
    {
        return ServiceCenterCase::assignedCases()
            ->notClosed()
            ->byStartedAt()
            ->byCreatedAt()
            ->get();
    }

    public function persist(ServiceCenterCase $case): ServiceCenterCase
    {
        $isNewCase = $case->getId() === null;

        $case =  DB::transaction(function () use ($case) {
            self::doPersist($case);

            $this->persistCaseEntry($case);

            $case = $this->refresh($case);

            return $case;
        });

        if ($isNewCase) {
            $this->dispatcher->dispatch(new CaseCreatedEvent($case));
        }

        return $case;
    }

    public function getById(int $caseId): ServiceCenterCase
    {
        return ServiceCenterCase::findOrFail($caseId);
    }

    public function getBySourceTelephonySession(TelephonySession $telephonySession): ServiceCenterCase
    {
        return ServiceCenterCase::hasSourceTelephonySession($telephonySession)->firstOrFail();
    }

    public function tryGetBySourceTelephonySession(TelephonySession $telephonySession): ?ServiceCenterCase
    {
        return ServiceCenterCase::hasSourceTelephonySession($telephonySession)->first();
    }

    public function getByTelephonySession(TelephonySession $telephonySession): ServiceCenterCase
    {
        $case = $this->tryGetByTelephonySession($telephonySession);

        if (null === $case) {
            throw new RuntimeException(
                sprintf('Could not find case for telephony sessions %s', $telephonySession->getId())
            );
        }

        return $case;
    }

    public function tryGetByTelephonySession(TelephonySession $telephonySession): ?ServiceCenterCase
    {
        return ServiceCenterCase::hasTelephonySession($telephonySession)->first();
    }

    public function tryGetByLead(Lead $lead): ?ServiceCenterCase
    {
        return ServiceCenterCase::belongsToLead($lead)->first();
    }

    public function refresh(ServiceCenterCase $case): ServiceCenterCase
    {
        return $this->getById($case->getId());
    }

    private function persistCaseEntry(ServiceCenterCase $case): void
    {
        /** @var ServiceCenterCaseEntry|null $persistedCaseEntry */
        $persistedCaseEntry = $case->currentCaseEntry()->first();
        $currentCaseEntry = $case->getCaseEntry();

        if (null !== $persistedCaseEntry && $persistedCaseEntry->equals($currentCaseEntry)) {
            return;
        }

        if (null !== $persistedCaseEntry) {
            self::doDelete($persistedCaseEntry);
        }

        $currentCaseEntry->case()->associate($case);

        self::doPersist($currentCaseEntry);
    }

    public function getCaseNoteById(int $caseNoteId): ServiceCenterCaseNote
    {
        /** @var ServiceCenterCaseNote $caseNote */
        $caseNote = ServiceCenterCaseNote::query()
            ->findOrFail($caseNoteId);

        return $caseNote;
    }

    public function insertCaseNote(ServiceCenterCaseNote $caseNote): ServiceCenterCaseNote
    {
        self::doInsert($caseNote);

        return $caseNote;
    }

    public function updateCaseNote(ServiceCenterCaseNote $caseNote): void
    {
        self::doUpdate($caseNote);
    }
}
