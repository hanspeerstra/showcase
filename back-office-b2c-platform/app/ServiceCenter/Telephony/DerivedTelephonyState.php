<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony;

use App\ServiceCenter\Telephony\Exception\NoSuchChannelException;
use App\Telephony\Number\ServicenumberLink;
use Assert\Assert;

class DerivedTelephonyState
{
    /** @var array */
    private $channels;
    /** @var bool */
    private $onHold;
    /** @var bool */
    private $forwarded;
    /** @var ServicenumberLink|null */
    private $matchedServiceNumberLink;
    /** @var bool */
    private $agentAnswered;

    /**
     * @param DerivedChannelState[] $channels
     * @param bool $forwarded indicates whether this is a forwarded call where the agent is no longer participating in
     */
    public function __construct(
        array $channels,
        bool $onHold,
        bool $forwarded,
        bool $agentAnswered
    ) {
        Assert::thatAll($channels)->isInstanceOf(DerivedChannelState::class);

        $this->channels = $channels;
        $this->onHold = $onHold;
        $this->forwarded = $forwarded;
        $this->agentAnswered = $agentAnswered;
    }

    public static function inactive(): self
    {
        return new static(
            [],
            false,
            false,
            false
        );
    }

    public function agentParticipatesInCall(): bool
    {
        return 0 !== count($this->channels) && !$this->forwarded;
    }

    public function hasAgentAnswered(): bool
    {
        return $this->agentAnswered;
    }

    /**
     * @return DerivedChannelState[]
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    public function getActiveChannelCount(): int
    {
        return count($this->channels);
    }

    public function hasChannel(string $channelId): bool
    {
        foreach ($this->channels as $channel) {
            if ($channel->getChannelId() === $channelId) {
                return true;
            }
        }

        return false;
    }

    public function getChannel(string $channelId): DerivedChannelState
    {
        foreach ($this->channels as $channel) {
            if ($channel->getChannelId() === $channelId) {
                return $channel;
            }
        }

        throw NoSuchChannelException::noActiveChannel($channelId);
    }

    public function hasChannelByReference(string $reference): bool
    {
        foreach ($this->channels as $channel) {
            if ($channel->getReference() === $reference) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws NoSuchChannelException thrown if there is no active channel with the given reference
     */
    public function getChannelByReference(string $reference): DerivedChannelState
    {
        foreach ($this->channels as $channel) {
            if ($channel->getReference() === $reference) {
                return $channel;
            }
        }

        throw NoSuchChannelException::noActiveChannelByReference($reference);
    }

    /**
     * Do we have every channel 'on hold'?
     */
    public function isOnHold(): bool
    {
        return $this->onHold;
    }

    /**
     * Returns true if this a forwarded call where the agent is no longer participating in.
     */
    public function isForwarded(): bool
    {
        return $this->forwarded;
    }

    public function getMatchedServiceNumberLink(): ?ServicenumberLink
    {
        return $this->matchedServiceNumberLink;
    }

    public function setMatchedServiceNumberLink(ServicenumberLink $matchedServiceNumberLink): self
    {
        $this->matchedServiceNumberLink = $matchedServiceNumberLink;
        return $this;
    }
}
