<?php declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Repository;

use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\AgentSession\AgentSessionLogEntry;
use App\ServiceCenter\AgentSession\Event\AgentSessionCaseAssignedBroadcastEvent;
use App\ServiceCenter\AgentSession\Event\AgentSessionCaseUnassignedBroadcastEvent;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Utils\Database\Contract\TransactionHandler;
use App\Utils\Storage\EloquentRepositoryHelperTrait;
use Illuminate\Contracts\Events\Dispatcher;

class AgentSessionLogRepository
{
    use EloquentRepositoryHelperTrait;

    /** @var Dispatcher */
    private $dispatcher;

    /** @var TransactionHandler */
    private $transactionHandler;

    public function __construct(Dispatcher $dispatcher, TransactionHandler $transactionHandler)
    {
        $this->dispatcher = $dispatcher;
        $this->transactionHandler = $transactionHandler;
    }

    public function persist(AgentSessionLogEntry $agentSessionLogEntry): AgentSessionLogEntry
    {
        return $this->transactionHandler->transactional(function () use ($agentSessionLogEntry) {
            /** @var AgentSessionLogEntry|null $currentAgentSessionLogEntry */
            $currentAgentSessionLogEntry = $agentSessionLogEntry
                ->getAgentSession()
                ->agentSessionLog()
                ->first();

            $this->dispatchEvents($currentAgentSessionLogEntry, $agentSessionLogEntry);

            if ($currentAgentSessionLogEntry !== null) {
                $this->delete($currentAgentSessionLogEntry);
            }

            return $this->insert($agentSessionLogEntry);
        });
    }

    public function hasCaseBeenAssignedToAgentSession(AgentSession $agentSession, ServiceCenterCase $case): bool
    {
        return AgentSessionLogEntry::withTrashed()
            ->newQuery()
            ->where('agent_session_id', '=', $agentSession->getId())
            ->where('case_id', '=', $case->getId())
            ->exists();
    }

    private function insert(AgentSessionLogEntry $agentSessionLogEntry): AgentSessionLogEntry
    {
        self::doInsert($agentSessionLogEntry);

        return $agentSessionLogEntry;
    }

    private function delete(AgentSessionLogEntry $agentSessionLogEntry): void
    {
        self::doDelete($agentSessionLogEntry);
    }

    private function dispatchEvents(
        ?AgentSessionLogEntry $currentAgentSessionLogEntry,
        AgentSessionLogEntry $newAgentSessionLogEntry
    ): void {
        $currentCaseId = $this->getCaseIdFromAgentSessionLogEntry($currentAgentSessionLogEntry);
        $newCaseId = $this->getCaseIdFromAgentSessionLogEntry($newAgentSessionLogEntry);

        if ($newCaseId !== null && $currentCaseId !== $newCaseId) {
            $this->dispatcher->dispatch(
                new AgentSessionCaseAssignedBroadcastEvent(
                    $newAgentSessionLogEntry->getAgentSession(),
                    $newAgentSessionLogEntry->getServiceCenterCase()
                )
            );
        }

        if ($newCaseId === null && $currentCaseId !== $newCaseId) {
            $this->dispatcher->dispatch(
                new AgentSessionCaseUnassignedBroadcastEvent(
                    $newAgentSessionLogEntry->getAgentSession(),
                    $currentAgentSessionLogEntry->getServiceCenterCase()
                )
            );
        }
    }

    private function getCaseIdFromAgentSessionLogEntry(?AgentSessionLogEntry $agentSessionLogEntry): ?int
    {
        if ($agentSessionLogEntry !== null && $agentSessionLogEntry->getServiceCenterCase() !== null) {
            return $agentSessionLogEntry->getServiceCenterCase()->getId();
        }

        return null;
    }
}
