<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony\Factory;

use App\ServiceCenter\Telephony\ChannelReferences;
use App\ServiceCenter\Telephony\DerivedChannelState;
use App\ServiceCenter\Telephony\DerivedTelephonyState;
use App\Telephony\ChannelMeta;
use App\Telephony\Session\Model\TelephonySession;
use App\Telephony\Session\State\Concerns\HasChannels;
use App\Telephony\Session\State\InboundCallMatchedState;
use App\Telephony\Session\State\StateInterface;
use App\Telephony\Session\State\Sub\ChannelState;
use App\Telephony\Session\StateMachineFactory;
use App\Telephony\Session\TelephonySessionStateMachine;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class DerivedTelephonyStateFactory
{
    /** @var StateMachineFactory */
    private $telephonyStateMachineFactory;

    public function __construct(StateMachineFactory $telephonyStateMachineFactory)
    {
        $this->telephonyStateMachineFactory = $telephonyStateMachineFactory;
    }

    public function createFromTelephonySession(?TelephonySession $telephonySession): DerivedTelephonyState
    {
        if (null === $telephonySession) {
            return DerivedTelephonyState::inactive();
        }

        $sessionStateMachine = $this->telephonyStateMachineFactory->fromSession($telephonySession);
        $telephonyState = $sessionStateMachine->getState();

        $channelLineNumbers = $this->determineActiveChannelLineNumbers($sessionStateMachine);

        $bidirectionalAudioConnections = $this->determineAudioConnections($telephonyState);

        /** @var ChannelState|null $agentChannel */
        $agentChannel = null;

        if ($telephonyState instanceof HasChannels) {
            $agentChannel = (new Collection($telephonyState->getActiveChannels()))
                ->first(
                    static function (ChannelState $channel): bool {
                        return self::isAgentChannel($channel);
                    }
                );
        }

        $isAudioConnectedToAgentFn = function (
            ChannelState $channel
        ) use ($agentChannel, $bidirectionalAudioConnections) {
            if (null === $agentChannel) {
                return false;
            }

            $channelId = $channel->getChannelId();

            return isset($bidirectionalAudioConnections[$channelId])
                && in_array($agentChannel->getChannelId(), $bidirectionalAudioConnections[$channelId]);
        };

        /** @var DerivedChannelState[] $activeChannels */
        $activeChannels = [];

        if ($telephonyState instanceof HasChannels) {
            foreach ($telephonyState->getActiveChannels() as $channel) {
                if (self::isAgentChannel($channel)) {
                    continue;
                }

                $companyIdMeta = $channel->getMetaField(ChannelMeta::COMPANY_ID);
                $companyId = $companyIdMeta === null ? null : (int) $companyIdMeta;

                $activeChannels[] = new DerivedChannelState(
                    $channel->getChannelId(),
                    $channelLineNumbers[$channel->getChannelId()],
                    $channel->getMetaField(ChannelMeta::REFERENCE),
                    $channel->getLocalPhoneNumber(),
                    $channel->getRemotePhoneNumber(),
                    $this->getChannelState($channel),
                    $isAudioConnectedToAgentFn($channel),
                    $companyId
                );
            }
        }

        $isAgentInCall = (new Collection($activeChannels))
            ->contains(static function (DerivedChannelState $channelState): bool {
                return $channelState->isAudioConnectedToAgent();
            });

        // All channels are connected to each other, but agent is not participating in call.
        $forwarded = !$isAgentInCall && count($activeChannels) > 0 && count($activeChannels) === count($bidirectionalAudioConnections);

        // No channels are connected to each other
        $onHold = 0 === count($bidirectionalAudioConnections);

        $agentAnswered = null !== $agentChannel && $agentChannel->getState() === ChannelState::STATE_ANSWERED;

        $result = new DerivedTelephonyState(
            $activeChannels,
            $onHold,
            $forwarded,
            $agentAnswered
        );

        if ($telephonyState instanceof InboundCallMatchedState) {
            $result->setMatchedServiceNumberLink($telephonyState->getNumberLink());
        }

        return $result;
    }

    private function getChannelState(ChannelState $channelState): string
    {
        switch ($channelState->getState()) {
            case ChannelState::STATE_CREATED:
            case ChannelState::STATE_TRYING:
            case ChannelState::STATE_PROGRESS:
                return DerivedChannelState::STATE_CONNECTING;
            case ChannelState::STATE_RINGING:
                return DerivedChannelState::STATE_RINGING;
            case ChannelState::STATE_ANSWERED:
                return DerivedChannelState::STATE_ANSWERED;
        }

        throw new InvalidArgumentException(sprintf('Unsupported channel state "%s"', $channelState->getState()));
    }

    /**
     * Returns the bidirectional audio connections between channels.
     *
     * @return string[][] mapping of channel IDs to list of channel IDs to which there is a bidirectional connection
     */
    private function determineAudioConnections(StateInterface $telephonyState): array
    {
        if (!$telephonyState instanceof HasChannels) {
            return [];
        }

        $unidirectionalSends = [];
        $bidirectionalAudioConnections = [];

        foreach ($telephonyState->getActiveChannels() as $channelState) {
            $sourceChannelId = $channelState->getChannelId();

            foreach ($channelState->getAudioSends() as $destinationChannelId) {
                $directionKey = $sourceChannelId . '-' . $destinationChannelId;
                $unidirectionalSends[$directionKey] = true;

                $reverseDirectionKey = $destinationChannelId . '-' . $sourceChannelId;
                if (isset($unidirectionalSends[$reverseDirectionKey])) {
                    // we have two-way audio connection
                    if (!isset($bidirectionalAudioConnections[$sourceChannelId])) {
                        $bidirectionalAudioConnections[$sourceChannelId] = [];
                    }
                    $bidirectionalAudioConnections[$sourceChannelId][] = $destinationChannelId;

                    if (!isset($bidirectionalAudioConnections[$destinationChannelId])) {
                        $bidirectionalAudioConnections[$destinationChannelId] = [];
                    }
                    $bidirectionalAudioConnections[$destinationChannelId][] = $sourceChannelId;
                }
            }
        }

        return $bidirectionalAudioConnections;
    }

    /**
     * @return array<string, int> mapping of channel IDs to line numbers (0-based)
     */
    private function determineActiveChannelLineNumbers(TelephonySessionStateMachine $sessionStateMachine): array
    {
        $removeChannel = static function (array $channels, string $channelId): array {
            if (!in_array($channelId, $channels, true)) {
                return $channels;
            }

            foreach ($channels as $key => $value) {
                if ($value === $channelId) {
                    $channels[$key] = null;

                    break;
                }
            }

            return $channels;
        };
        $addChannel = static function (array $channels, string $channelId): array {
            if (in_array($channelId, $channels, true)) {
                return $channels;
            }

            $inserted = false;

            foreach ($channels as $key => $value) {
                if (null === $value) {
                    $channels[$key] = $channelId;
                    $inserted = true;

                    break;
                }
            }

            if (!$inserted) {
                $channels[] = $channelId;
            }

            return $channels;
        };

        $channels = [];

        foreach ($sessionStateMachine->getFullStateHistory() as $state) {
            if ($state instanceof HasChannels) {
                foreach ($state->getChannels() as $channelState) {
                    // ignore agent channel
                    if (self::isAgentChannel($channelState)) {
                        continue;
                    }

                    $channelId = $channelState->getChannelId();

                    if ($channelState->isFinal()) {
                        $channels = $removeChannel($channels, $channelId);
                    } else {
                        $channels = $addChannel($channels, $channelId);
                    }
                }
            }
        }

        $lineNumbers = [];

        foreach ($channels as $index => $channelId) {
            if (null !== $channelId) {
                $lineNumbers[$channelId] = $index;
            }
        }

        return $lineNumbers;
    }

    private static function isAgentChannel(ChannelState $channel): bool
    {
        return ChannelReferences::AGENT === $channel->getMetaField(ChannelMeta::REFERENCE);
    }
}
