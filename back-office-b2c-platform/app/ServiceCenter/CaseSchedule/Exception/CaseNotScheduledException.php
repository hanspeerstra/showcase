<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseSchedule\Exception;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Exception;

class CaseNotScheduledException extends Exception
{
    public static function forCase(ServiceCenterCase $case): self
    {
        return new static(sprintf('Case %d is not scheduled', $case->getId()));
    }
}
