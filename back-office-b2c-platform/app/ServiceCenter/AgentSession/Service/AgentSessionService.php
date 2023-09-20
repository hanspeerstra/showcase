<?php declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Service;

use App\Auth\User;
use App\InternalPhone\InternalPhone;
use App\Repositories\Backend\Auth\UserRepository;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\AgentSession\AgentSessionLogEntry;
use App\ServiceCenter\AgentSession\AgentSessionStatus;
use App\ServiceCenter\AgentSession\Event\AgentSessionChangedBroadcastEvent;
use App\ServiceCenter\AgentSession\Event\AgentSessionStartedEvent;
use App\ServiceCenter\AgentSession\Event\AgentSessionTelephonySessionDetachedBroadcastEvent;
use App\ServiceCenter\AgentSession\Exception\CannotEndAgentSessionException;
use App\ServiceCenter\AgentSession\Repository\AgentSessionLogRepository;
use App\ServiceCenter\AgentSession\Repository\AgentSessionRepository;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\WorkGroup\WorkGroup;
use App\Telephony\Session\Model\TelephonySession;
use App\Utils\Database\Contract\TransactionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use UnexpectedValueException;

class AgentSessionService
{
    /** @var AgentSessionRepository */
    private $agentSessionRepository;

    /** @var AgentSessionLogRepository */
    private $agentSessionLogRepository;

    /** @var ServiceCenterCaseRepository */
    private $serviceCenterCaseRepository;

    /** @var Dispatcher */
    private $dispatcher;

    /** @var TransactionHandler */
    private $transactionHandler;

    /** @var Connection */
    private $connection;

    /** @var UserRepository */
    private $userRepository;

    public function __construct(
        AgentSessionRepository $agentSessionRepository,
        AgentSessionLogRepository $agentSessionLogRepository,
        ServiceCenterCaseRepository $serviceCenterCaseRepository,
        Dispatcher $dispatcher,
        TransactionHandler $transactionHandler,
        Connection $connection,
        UserRepository $userRepository
    ) {
        $this->agentSessionRepository = $agentSessionRepository;
        $this->agentSessionLogRepository = $agentSessionLogRepository;
        $this->serviceCenterCaseRepository = $serviceCenterCaseRepository;
        $this->dispatcher = $dispatcher;
        $this->transactionHandler = $transactionHandler;
        $this->connection = $connection;
        $this->userRepository = $userRepository;
    }

    /**
     * @param WorkGroup[] $workGroups
     */
    public function createAndStartSession(
        User $user,
        InternalPhone $internalPhone,
        bool $automaticallyAssignCase,
        ?int $priority,
        WorkGroup ...$workGroups
    ): AgentSession {
        $agentSession = AgentSession::makeInstance($user, $internalPhone, $automaticallyAssignCase, $priority);

        $agentSession = $this->transactionHandler->transactional(
            function () use ($agentSession, $workGroups) {
                $agentSession = $this->agentSessionRepository->insert($agentSession);

                $this->agentSessionRepository->persistWorkGroups($agentSession, ...$workGroups);

                $agentSessionStatus = $this->determineInitialAgentSessionStatus($agentSession);

                $this->updateAgentSessionLogEntry(
                    $agentSession,
                    $agentSessionStatus,
                    null,
                    null
                );

                return $this->agentSessionRepository->getById($agentSession->getId());
            }
        );

        $this->dispatcher->dispatch(new AgentSessionStartedEvent());

        return $agentSession;
    }

    public function updateAgentSessionLogEntry(
        AgentSession $agentSession,
        AgentSessionStatus $agentSessionStatus,
        ?ServiceCenterCase $serviceCenterCase,
        ?TelephonySession $telephonySession
    ): AgentSessionLogEntry {
        $agentSessionLogEntry = AgentSessionLogEntry::new(
            $agentSession,
            $agentSessionStatus,
            $serviceCenterCase,
            $telephonySession
        );

        return $this->agentSessionLogRepository->persist($agentSessionLogEntry);
    }

    /**
     * @throws CannotEndAgentSessionException
     */
    public function endSession(AgentSession $agentSession): void
    {
        $assignedCases = $this->serviceCenterCaseRepository->findCasesByAssignedAgent($agentSession->getUser());

        if ($assignedCases !== []) {
            throw CannotEndAgentSessionException::hasCasesAssigned($agentSession, $assignedCases);
        }

        $this->agentSessionRepository->delete($agentSession);
    }

    public function attachTelephonySession(AgentSession $agentSession, TelephonySession $telephonySession): AgentSession
    {
        if ($agentSession->hasActiveTelephonySession()) {
            throw new UnexpectedValueException(
                'Cannot attach telephony session to agent session; agent already has an active telephony session'
            );
        }

        $currentLogEntry = $agentSession->getAgentSessionLogEntry();

        $updatedLogEntry = $this->updateAgentSessionLogEntry(
            $agentSession,
            $currentLogEntry->getStatus(),
            $currentLogEntry->getServiceCenterCase(),
            $telephonySession
        );

        return $updatedLogEntry->getAgentSession();
    }

    public function detachTelephonySession(AgentSession $agentSession): AgentSession
    {
        $agentSessionLogEntry = $agentSession->getAgentSessionLogEntry();

        $telephonySession = $agentSessionLogEntry->getTelephonySession();
        if ($telephonySession === null) {
            throw new UnexpectedValueException(
                sprintf('AgentSession (ID: %s) does not have a TelephonySession', $agentSession->getId())
            );
        }

        $agentSession = $this->updateAgentSessionLogEntry(
            $agentSession,
            $agentSessionLogEntry->getStatus(),
            $agentSessionLogEntry->getServiceCenterCase(),
            null
        )->getAgentSession();

        $this->dispatcher->dispatch(
            new AgentSessionTelephonySessionDetachedBroadcastEvent($agentSession)
        );

        return $agentSession;
    }

    public function setInitialAgentSessionLogEntry(AgentSession $agentSession): AgentSession
    {
        $agentSessionStatus = $this->determineInitialAgentSessionStatus(
            $agentSession
        );

        return $this->updateAgentSessionLogEntry(
            $agentSession,
            $agentSessionStatus,
            null,
            null
        )->getAgentSession();
    }

    public function assignCasesAutomatically(AgentSession $agentSession, bool $assignCasesAutomatically, ?int $priority): AgentSession
    {
        if ($assignCasesAutomatically === true && $priority === null) {
            throw new UnexpectedValueException('Priority must be given when automaticallyAssign cases is on');
        }

        if ($assignCasesAutomatically === false && $priority !== null) {
            throw new UnexpectedValueException('Priority must be null when automaticallyAssign cases is off');
        }

        $agentSession->setAutomaticallyAssignCase($assignCasesAutomatically);
        $agentSession->setPriority($priority);

        $this->transactionHandler->transactional(function () use ($agentSession) {
            $this->agentSessionRepository->update($agentSession);

            if (!$agentSession->hasActiveCase()) {
                $this->setInitialAgentSessionLogEntry($agentSession);
            }
        });

        $this->dispatcher->dispatch(
            new AgentSessionChangedBroadcastEvent($agentSession)
        );

        return $agentSession;
    }

    /**
     * @return User[]
     */
    public function getUniqueAgents(): iterable
    {
        $sql = <<<'SQL'
            SELECT user_id
            FROM sc_agent_sessions
            WHERE sc_agent_sessions.created_at > CURRENT_DATE - INTERVAL 90 DAY
            GROUP BY user_id
SQL;

        $agentIdList = [];
        foreach ($this->connection->getPdo()->query($sql) as $record) {
            $agentIdList[] = $record['user_id'];
        }

        return $this->userRepository->getByIdList(...$agentIdList);
    }

    private function determineInitialAgentSessionStatus(AgentSession $agentSession): AgentSessionStatus
    {
        if ($agentSession->isAutomaticallyAssignCase() === true) {
            return new AgentSessionStatus(AgentSessionStatus::AWAITING_CASE);
        }

        return new AgentSessionStatus(AgentSessionStatus::MANUAL_QUEUE);
    }
}
