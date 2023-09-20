<?php

declare(strict_types=1);

namespace App\ServiceCenter\Listener;

use App\ServiceCenter\AgentSession\Repository\AgentSessionRepository;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\ServiceCenterCase\Repository\ServiceCenterCaseRepository;
use App\ServiceCenter\ServiceCenterCase\Service\ServiceCenterCaseService;
use App\ServiceCenter\Telephony\ChannelReferences;
use App\Telephony\ChannelMeta;
use App\Telephony\Session\State\Concerns\HasChannels;
use App\Telephony\Session\State\Sub\ChannelState;
use App\Telephony\Session\StateTransitionedEvent;
use App\Utils\Database\Contract\TransactionHandler;
use Psr\Log\LoggerInterface;

class AgentRejectsTelephonyCaseListener
{
    private ServiceCenterCaseRepository $serviceCenterCaseRepository;
    private ServiceCenterCaseService $serviceCenterCaseService;
    private AgentSessionService $agentSessionService;
    private TransactionHandler $transactionHandler;
    private AgentSessionRepository $agentSessionRepository;
    private LoggerInterface $logger;

    public function __construct(
        ServiceCenterCaseRepository $serviceCenterCaseRepository,
        ServiceCenterCaseService $serviceCenterCaseService,
        AgentSessionService $agentSessionService,
        TransactionHandler $transactionHandler,
        AgentSessionRepository $agentSessionRepository,
        LoggerInterface $logger
    ) {
        $this->serviceCenterCaseRepository = $serviceCenterCaseRepository;
        $this->serviceCenterCaseService = $serviceCenterCaseService;
        $this->agentSessionService = $agentSessionService;
        $this->transactionHandler = $transactionHandler;
        $this->agentSessionRepository = $agentSessionRepository;
        $this->logger = $logger;
    }

    public function onTelephonyStateChanged(StateTransitionedEvent $event): void
    {
        $state = $event->getCurrentState();
        $prevState = $event->getPreviousState();

        if (!$state instanceof HasChannels || !$prevState instanceof HasChannels) {
            return;
        }

        $rejectedAgentChannel = null;

        $hasAgentReference = static function (ChannelState $channel) {
            return $channel->getMetaField(ChannelMeta::REFERENCE) === ChannelReferences::AGENT;
        };

        $agentChannels = $state->getChannels($hasAgentReference);
        $prevAgentChannels = $prevState->getChannels($hasAgentReference);

        /**
         * When the agent channel is final and the previous state is not ANSWERED then the agent has rejected the call.
         *
         * @var ChannelState $channel
         * @var ChannelState|null $prevChannel
         */
        foreach (array_map(null, $agentChannels, $prevAgentChannels) as [$channel, $prevChannel]) {
            if (
                $channel->getState() === ChannelState::STATE_FAULTED &&
                !$prevChannel->isFinal() &&
                $prevChannel->getState() !== ChannelState::STATE_ANSWERED
            ) {
                $rejectedAgentChannel = $channel;
                break;
            }
        }

        if ($rejectedAgentChannel === null) {
            // No rejected agent channel detected
            return;
        }

        // We just transitioned into final state for agent channel, and it hasn't been answered before.
        $case = $this->serviceCenterCaseRepository->tryGetBySourceTelephonySession($event->getTelephonySession());

        if (null === $case) {
            // Telephony session is not source telephony session, so probably agent started a telephony session in a case; don't unassign the case.
            return;
        }

        $agentSessionId = $rejectedAgentChannel->getMetaField(ChannelMeta::AGENT_SESSION_ID);

        if ($agentSessionId === null) {
            // Missing agent session ID. This is a problem, but nothing we can do right now but warn about it.
            $this->logger->warning(
                'Detected rejected agent channel, but no agent session ID data was found. ' .
                'Cannot detach and unassign automatically.',
                [
                    'telephonySessionId' => $event->getTelephonySession()->getId(),
                    'channelId' => $rejectedAgentChannel->getChannelId(),
                    'event' => $event->getSessionStateMachine()->getLastEvent()->jsonSerialize(),
                ]
            );
            return;
        }

        $agentSession = $this->agentSessionRepository->getById((int) $agentSessionId);

        $this->transactionHandler->doInLockedTransaction($case, function () use ($case, $agentSession) {
            $this->agentSessionService->detachTelephonySession($agentSession);
            // Refresh the case because the agent session attached to it has changed
            $case = $this->serviceCenterCaseRepository->refresh($case);
            $this->serviceCenterCaseService->unassignAgentFromCaseForAgentSession($case, $agentSession);
        });
    }
}
