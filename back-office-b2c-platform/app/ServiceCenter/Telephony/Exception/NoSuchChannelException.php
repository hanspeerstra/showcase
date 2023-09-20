<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony\Exception;

use Exception;

class NoSuchChannelException extends Exception
{
    public static function noActiveChannelByReference(string $reference): self
    {
        return new static(sprintf('There is no active channel with reference "%s"', $reference));
    }

    public static function noActiveChannel(string $channelId): self
    {
        return new static(sprintf('There is no active channel with Id "%s"', $channelId));
    }
}
