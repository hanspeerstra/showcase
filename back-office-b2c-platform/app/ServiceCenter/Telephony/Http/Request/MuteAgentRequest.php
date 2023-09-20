<?php

declare(strict_types=1);

namespace App\ServiceCenter\Telephony\Http\Request;

use App\Auth\User;
use App\ServiceCenter\AgentSession\AgentSession;
use Illuminate\Foundation\Http\FormRequest;

class MuteAgentRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }

    public function getAgentSession(): AgentSession
    {
        return $this->getAgent()->getActiveAgentSession();
    }

    private function getAgent(): User
    {
        return $this->user();
    }
}
