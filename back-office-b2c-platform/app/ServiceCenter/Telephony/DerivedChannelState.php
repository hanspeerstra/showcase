<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony;

use Assert\Assert;
use Propaganistas\LaravelPhone\PhoneNumber;

class DerivedChannelState
{
    public const STATE_CONNECTING = 'connecting';
    public const STATE_RINGING = 'ringing';
    public const STATE_ANSWERED = 'answered';

    /** @var string */
    private $channelId;
    /** @var int */
    private $lineNumber;
    /** @var string|null */
    private $reference;
    /** @var PhoneNumber|null */
    private $localPhoneNumber;
    /** @var PhoneNumber|null */
    private $remotePhoneNumber;
    /** @var string */
    private $state;
    /** @var bool */
    private $audioConnectedToAgent;
    /** @var int|null */
    private $companyId;

    /**
     * @param int $lineNumber line number (0-based)
     */
    public function __construct(
        string $channelId,
        int $lineNumber,
        ?string $reference,
        ?PhoneNumber $localPhoneNumber,
        ?PhoneNumber $remotePhoneNumber,
        string $state,
        bool $audioConnectedToAgent,
        ?int $companyId
    ) {
        Assert::that($state)->inArray([self::STATE_CONNECTING, self::STATE_RINGING, self::STATE_ANSWERED]);

        $this->channelId = $channelId;
        $this->lineNumber = $lineNumber;
        $this->reference = $reference;
        $this->localPhoneNumber = $localPhoneNumber;
        $this->remotePhoneNumber = $remotePhoneNumber;
        $this->state = $state;
        $this->audioConnectedToAgent = $audioConnectedToAgent;
        $this->companyId = $companyId;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    /**
     * Returns the line number (0-based).
     */
    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getRemotePhoneNumber(): ?PhoneNumber
    {
        return $this->remotePhoneNumber;
    }

    public function getLocalPhoneNumber(): ?PhoneNumber
    {
        return $this->localPhoneNumber;
    }

    /**
     * @return string one of the STATE_x constants
     */
    public function getState(): string
    {
        return $this->state;
    }

    public function isAudioConnectedToAgent(): bool
    {
        return $this->audioConnectedToAgent;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }
}
