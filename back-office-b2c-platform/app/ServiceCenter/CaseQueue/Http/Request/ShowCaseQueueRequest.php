<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseQueue\Http\Request;

use App\Auth\User;
use App\ServiceCenter\AgentSession\AgentSession;
use Illuminate\Foundation\Http\FormRequest;

class ShowCaseQueueRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }

    public function getAgent(): User
    {
        return $this->user();
    }

    public function getAgentSession(): AgentSession
    {
        return $this->getAgent()->getActiveAgentSession();
    }
}
