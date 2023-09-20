<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Exception;

use App\Auth\User;
use RuntimeException;

class NoActiveAgentSessionException extends RuntimeException
{
    public static function forUser(User $user): self
    {
        return new static(sprintf('User %d has no active agent session', $user->getId()));
    }
}
