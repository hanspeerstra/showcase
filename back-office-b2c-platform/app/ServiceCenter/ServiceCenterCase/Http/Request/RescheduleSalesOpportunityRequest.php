<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Auth\User;
use App\ServiceCenter\AgentSession\AgentSession;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property-read ServiceCenterCase $case
 */
class RescheduleSalesOpportunityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'scheduleDate' => ['required', 'date', 'after_or_equal:now'],
            'note' => ['sometimes', 'nullable'],
        ];
    }

    public function getScheduleDate(): CarbonImmutable
    {
        return new CarbonImmutable(
            $this->input('scheduleDate')
        );
    }

    public function getNote(): ?string
    {
        return $this->input('note');
    }

    public function getAgent(): User
    {
        return $this->user();
    }

    public function getAgentSession(): AgentSession
    {
        return $this->getAgent()->getActiveAgentSession();
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }
}
