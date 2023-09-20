<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Event;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Telephony\Session\Model\TelephonySession;
use Neves\Events\Contracts\TransactionalEvent;

class CaseWasUnassignedEvent implements TransactionalEvent
{
    /** @var ServiceCenterCase */
    private $case;

    /** @var TelephonySession|null */
    private $telephonySession;

    public function __construct(ServiceCenterCase $case, ?TelephonySession $telephonySession)
    {
        $this->case = $case;
        $this->telephonySession = $telephonySession;
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    public function getTelephonySession(): ?TelephonySession
    {
        return $this->telephonySession;
    }
}
