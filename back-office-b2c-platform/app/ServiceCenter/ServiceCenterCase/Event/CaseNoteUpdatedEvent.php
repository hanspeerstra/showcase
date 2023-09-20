<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Event;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseNote;
use Neves\Events\Contracts\TransactionalEvent;

class CaseNoteUpdatedEvent implements TransactionalEvent
{
    /** @var ServiceCenterCaseNote */
    private $caseNote;

    public function __construct(ServiceCenterCaseNote $caseNote)
    {
        $this->caseNote = $caseNote;
    }

    public function getCaseNote(): ServiceCenterCaseNote
    {
        return $this->caseNote;
    }
}
