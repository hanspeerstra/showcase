<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase;

class ContactRequestType
{
    public const APPOINTMENT = 'appointment';
    public const QUOTE = 'quote';
    public const QUOTE_COMPARISON = 'quote_comparison';
    public const QUOTE_FOLLOW_UP = 'quote_follow_up';
    public const CALL = 'call';
    public const CALLBACK_REQUEST = 'callback_request';
    public const ZOOFY_APPOINTMENT = 'zoofy_appointment';
    public const EXTERNAL_QUOTE_REQUEST = 'external_quote_request';

    public static function getAll(): array
    {
        return array_values(
            (new \ReflectionClass(self::class))->getConstants()
        );
    }
}
