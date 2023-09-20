<?php

declare(strict_types=1);

namespace App\Utils\Bool\Exception;

use InvalidArgumentException;

class InvalidBoolean extends InvalidArgumentException
{
    public static function withData($data): self
    {
        return new self(
            sprintf('%s can not be converted to boolean.', print_r($data, true))
        );
    }
}
