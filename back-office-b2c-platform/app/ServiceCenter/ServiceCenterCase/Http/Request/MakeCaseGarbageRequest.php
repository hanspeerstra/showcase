<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Auth\User;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseGarbageReason;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseNote;
use Arcanedev\Support\Http\FormRequest;

class MakeCaseGarbageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'garbageReasonLabel' => ['required', 'string'],
            'note' => ['sometimes', 'string'],
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

    public function getGarbageReason(): ServiceCenterCaseGarbageReason
    {
        return ServiceCenterCaseGarbageReason::query()
            ->belongsToLabel(
                $this->input('garbageReasonLabel')
            )
            ->firstOrFail();
    }

    public function getNote(): ?ServiceCenterCaseNote
    {
        $note = $this->input('note');
        if ($note !== null) {
            return ServiceCenterCaseNote::makeInstance(
                $this->getCase(),
                $this->getAgent(),
                $note
            );
        }

        return null;
    }
}
