<?php

declare(strict_types=1);

namespace App\Utils\Bool;

use App\Utils\Bool\Exception\InvalidBoolean;

class BoolUtil
{
    /**
     * @throws InvalidBoolean
     */
    public static function filterFromData($data): bool
    {
        if (static::isTruthy($data)) {
            return true;
        }
        if (static::isFalsy($data)) {
            return false;
        }

        throw InvalidBoolean::withData($data);
    }

    public static function isTruthy($data): bool
    {
        if (is_string($data) && strtolower($data) === 'true') {
            return true;
        }

        return $data === 1
            || $data === '1'
            || $data === true;
    }

    public static function isFalsy($data): bool
    {
        if (is_string($data) && strtolower($data) === 'false') {
            return true;
        }

        return $data === 0
            || $data === '0'
            || $data === false;
    }
}
