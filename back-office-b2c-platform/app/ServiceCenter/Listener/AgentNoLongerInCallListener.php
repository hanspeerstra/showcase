<?php

declare(strict_types=1);

namespace App\ServiceCenter\Listener;

use App\ServiceCenter\AgentSession\Repository\AgentSessionRepository;
use App\ServiceCenter\AgentSession\Service\AgentSessionService;
use App\ServiceCenter\Telephony\ChannelReferences;
use App\Telephony\ChannelMeta;
use App\Telephony\Session\State\Concerns\HasChannels;
use App\Telephony\Session\State\StateInterface;
use App\Telephony\Session\State\Sub\ChannelState;
use App\Telephony\Session\StateTransitionedEvent;
use LogicException;
use Psr\Log\LoggerInterface;

class AgentNoLongerInCallListener
{
    /** @var AgentSessionRepository */
    private $agentSessionRepository;
    /** @var AgentSessionService */
    private $agentSessionService;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        AgentSessionRepository $agentSessionRepository,
        AgentSessionService $agentSessionService,
        LoggerInterface $logger
    ) {
        $this->agentSessionRepository = $agentSessionRepository;
        $this->agentSessionService = $agentSessionService;
        $this->logger = $logger;
    }

    public function onTelephonyStateChanged(StateTransitionedEvent $event): void
    {
        if ($event->hasTransitionedTo(function (StateInterface $telephonyState) {
            return $this->agentChannelEnded($telephonyState);
        })) {
            $agentChannel = self::findLatestFinalAgentChannel($event->getCurrentState());

            if (null === $agentChannel) {
                throw new LogicException('should find latest final agent channel');
            }

            $agentSession = null;
            if (null !== $agentChannel->getMetaField(ChannelMeta::AGENT_SESSION_ID)) {
                $agentSession = $this->agentSessionRepository->getById(
                    (int) $agentChannel->getMetaField(ChannelMeta::AGENT_SESSION_ID)
                );
            }

            // Agent could have intialized hangup (e.g. by hanging up phone)
            if (null === $agentSession || !$agentSession->hasActiveTelephonySession()) {
                return;
            }

            $this->agentSessionService->detachTelephonySession($agentSession);
        }
    }

    private function agentChannelEnded(StateInterface $telephonyState): bool
    {
        $agentChannel = self::findLatestFinalAgentChannel($telephonyState);

        return null !== $agentChannel;
    }

    private static function findLatestFinalAgentChannel(StateInterface $telephonyState): ?ChannelState
    {
        if (!$telephonyState instanceof HasChannels) {
            return null;
        }

        $finalAgentChannels = $telephonyState->getChannels(static function (ChannelState $channel): bool {
            return ChannelReferences::AGENT === $channel->getMetaField(ChannelMeta::REFERENCE)
                && $channel->isFinal();
        });

        if (count($finalAgentChannels) > 0) {
            return end($finalAgentChannels);
        }

        return null;
    }
}
