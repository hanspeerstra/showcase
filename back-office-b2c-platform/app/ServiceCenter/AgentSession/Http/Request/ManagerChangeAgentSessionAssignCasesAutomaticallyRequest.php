<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Http\Request;

use App\Auth\User;
use App\ServiceCenter\AgentSession\AgentSession;
use App\Utils\Bool\BoolUtil;
use Illuminate\Foundation\Http\FormRequest;

class ManagerChangeAgentSessionAssignCasesAutomaticallyRequest extends FormRequest
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
            'automaticallyAssignCase' => 'required|boolean',
            'priority' => 'nullable|integer',
        ];
    }

    public function getTargetAgentSession(): AgentSession
    {
        return AgentSession::query()
            ->findOrFail($this->input('agentSessionId'));
    }

    public function getAssignCasesAutomatically(): bool
    {
        return BoolUtil::filterFromData($this->get('automaticallyAssignCase'));
    }

    public function getPriority(): ?int
    {
        return $this->get('priority');
    }

    private function getAgent(): User
    {
        return $this->user();
    }
}
