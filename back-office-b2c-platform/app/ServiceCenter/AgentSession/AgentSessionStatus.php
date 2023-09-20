<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession;

use InvalidArgumentException;

class AgentSessionStatus
{
    public const AWAITING_CASE = 'awaiting_case';
    public const HANDLE_CASE = 'handle_case';
    public const MANUAL_QUEUE = 'manual_queue';

    private const STATUSES = [
        self::AWAITING_CASE,
        self::HANDLE_CASE,
        self::MANUAL_QUEUE,
    ];

    /** @var string */
    private $status;

    public function __construct(string $status)
    {
        if (!in_array($status, self::STATUSES)) {
            throw new InvalidArgumentException(sprintf('Invalid status "%s"', $status));
        }

        $this->status = $status;
    }

    public function getValue(): string
    {
        return $this->status;
    }

    public function equals(AgentSessionStatus $other): bool
    {
        return $this->status === $other->status;
    }
}
