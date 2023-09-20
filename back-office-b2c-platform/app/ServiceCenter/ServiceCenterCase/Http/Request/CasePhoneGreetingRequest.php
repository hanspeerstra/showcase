<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Auth\User;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Arcanedev\Support\Http\FormRequest;

/**
 * @property ServiceCenterCase $case
 */
class CasePhoneGreetingRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    public function getAgent(): User
    {
        return $this->user();
    }
}
