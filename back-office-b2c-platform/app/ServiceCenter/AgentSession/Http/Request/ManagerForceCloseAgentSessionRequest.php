<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Http\Request;

use App\Auth\User;
use App\ServiceCenter\AgentSession\AgentSession;
use Illuminate\Foundation\Http\FormRequest;

class ManagerForceCloseAgentSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->getAgent()
            ->can('Service center manager');
    }

    public function rules(): array
    {
        return [
            'agentSessionId' => 'required|int',
        ];
    }

    public function getTargetAgentSession(): AgentSession
    {
        return AgentSession::query()
            ->findOrFail($this->input('agentSessionId'));
    }

    private function getAgent(): User
    {
        return $this->user();
    }
}
