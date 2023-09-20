<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Event;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Neves\Events\Contracts\TransactionalEvent;

class CaseCreatedEvent implements TransactionalEvent
{
    /** @var ServiceCenterCase */
    private $case;

    public function __construct(ServiceCenterCase $case)
    {
        $this->case = $case;
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }
}
