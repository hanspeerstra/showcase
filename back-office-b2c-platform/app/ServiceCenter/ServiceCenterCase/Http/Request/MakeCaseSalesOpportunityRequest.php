<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Auth\User;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Arcanedev\Support\Http\FormRequest;
use Carbon\CarbonImmutable;

/**
 * @property ServiceCenterCase $case
 */
class MakeCaseSalesOpportunityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['required'],
            'scheduleDate' => ['required', 'date', 'after_or_equal:now'],
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

    public function getNote(): string
    {
        return $this->input('note');
    }

    public function getScheduleDate(): CarbonImmutable
    {
        return new CarbonImmutable(
            $this->input('scheduleDate')
        );
    }
}
