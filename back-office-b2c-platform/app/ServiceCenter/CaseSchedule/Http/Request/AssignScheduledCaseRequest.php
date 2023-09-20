<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseSchedule\Http\Request;

use App\Auth\User;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property-read ServiceCenterCase $case
 * @method User user()
 */
class AssignScheduledCaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    public function getAgentSession(): AgentSession
    {
        return $this->user()->getActiveAgentSession();
    }
}
