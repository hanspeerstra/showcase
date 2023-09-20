<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Command;

use App\ServiceCenter\AgentSession\AgentSessionLifetimeConfig;
use App\ServiceCenter\AgentSession\Repository\AgentSessionRepository;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Psr\Log\LoggerInterface;
use Throwable;

class LogoutInactiveAgentSessionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service-center:logout-inactive-agent-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Logout agent sessions which have been inactive for a period of time';

    public function handle(
        AgentSessionService $agentSessionService,
        AgentSessionRepository $agentSessionRepository,
        LoggerInterface $logger,
        AgentSessionLifetimeConfig $agentSessionLifetimeConfig
    ): void {
        $inactiveSince = (new DateTimeImmutable())
            ->modify(sprintf('-%d minutes', $agentSessionLifetimeConfig->getAgentSessionLifetimeInMinutes()));

        $agentSessions = $agentSessionRepository->findInactiveAgentSessions($inactiveSince);

        foreach ($agentSessions as $agentSession) {
            try {
                $agentSessionService->endSession($agentSession);

                $logger->debug(
                    'Logged out inactive agent session',
                    ['agentSessionId' => $agentSession->getId()]
                );
            } catch (Throwable $exception) {
                $logger->warning(
                    'Could not end inactive agent session',
                    [
                        'agentSessionId' => $agentSession->getId(),
                        'exception' => $exception,
                    ]
                );
            }
        }
    }
}
