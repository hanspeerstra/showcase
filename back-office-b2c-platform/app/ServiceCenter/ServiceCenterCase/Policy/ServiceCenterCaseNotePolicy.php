<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Policy;

use App\Auth\User;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseNote;

class ServiceCenterCaseNotePolicy
{
    public function update(User $user, ServiceCenterCaseNote $caseNote): bool
    {
        return $user->getId() === $caseNote->getAgent()->getId();
    }
}
