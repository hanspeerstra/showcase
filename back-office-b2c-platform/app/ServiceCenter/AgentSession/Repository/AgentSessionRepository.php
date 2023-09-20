<?php
declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Repository;

use App\Auth\User;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Telephony\Session\Model\TelephonySession;
use App\Utils\Storage\EloquentRepositoryHelperTrait;
use DateTimeInterface;
use Illuminate\Support\Collection;

class AgentSessionRepository
{
    use EloquentRepositoryHelperTrait;

    public function insert(AgentSession $agentSession): AgentSession
    {
        self::doInsert($agentSession);

        return $agentSession;
    }

    public function delete(AgentSession $agentSession): void
    {
        self::doDelete($agentSession);
    }

    /**
     * @param WorkGroup[] $workGroups
     */
    public function persistWorkGroups(AgentSession $agentSession, WorkGroup ...$workGroups): void
    {
        $workGroupIdList = array_map(
            static function (WorkGroup $workGroup): int {
                return $workGroup->getId();
            },
            $workGroups
        );

        $agentSession->workGroups()->sync($workGroupIdList);
    }

    public function update(AgentSession $agentSession): void
    {
        self::doUpdate($agentSession);
    }

    public function getById(int $id): AgentSession
    {
        /** @var AgentSession $agentSession */
        $agentSession = AgentSession::query()
            ->findOrFail($id);

        return $agentSession;
    }

    public function getByAgent(User $agent): ?AgentSession
    {
        return AgentSession::query()
            ->whereUser($agent)
            ->first();
    }

    public function getByTelephonySession(TelephonySession $telephonySession): ?AgentSession
    {
        return AgentSession::query()
            ->whereTelephonySession($telephonySession)
            ->first();
    }

    /**
     * @return Collection|AgentSession[]
     */
    public function findAgentSessionsAvailableForInteractiveCaseAssignment(): iterable
    {
        return AgentSession::query()
            ->availableForInteractiveCase()
            ->orderByPriority()
            ->get();
    }

    /**
     * @return Collection|AgentSession[]
     */
    public function findAgentSessionsAvailableForPassiveCaseAssignment(): iterable
    {
        return AgentSession::query()
            ->availableForPassiveCase()
            ->orderByPriority()
            ->get();
    }

    /**
     * @return AgentSession[]
     */
    public function findActiveAgentSessions(): iterable
    {
        return AgentSession::all();
    }

    /**
     * @return AgentSession[]
     */
    public function findInactiveAgentSessions(DateTimeInterface $inactiveSince): iterable
    {
        return AgentSession::query()
            ->whereUserInactiveSince($inactiveSince)
            ->get();
    }
}
