<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Auth\User;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property ServiceCenterCase $case
 */
class CloseCaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['nullable'],
        ];
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    public function getAgent(): User
    {
        return $this->user();
    }

    public function getAgentSession(): AgentSession
    {
        return $this->getAgent()->getActiveAgentSession();
    }

    public function getNote(): ?string
    {
        return $this->input('note');
    }
}
