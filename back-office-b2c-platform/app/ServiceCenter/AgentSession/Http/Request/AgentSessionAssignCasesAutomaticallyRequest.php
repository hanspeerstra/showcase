<?php

declare(strict_types=1);

namespace App\ServiceCenter\AgentSession\Http\Request;

use App\Auth\User;
use App\Utils\Bool\BoolUtil;
use Arcanedev\Support\Http\FormRequest;

class AgentSessionAssignCasesAutomaticallyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'automaticallyAssignCase' => 'required|boolean',
            'priority' => 'nullable|integer',
        ];
    }

    public function getAssignCasesAutomatically(): bool
    {
        return BoolUtil::filterFromData($this->get('automaticallyAssignCase'));
    }

    public function getPriority(): ?int
    {
        return $this->get('priority');
    }

    public function getAgent(): User
    {
        return $this->user();
    }
}
