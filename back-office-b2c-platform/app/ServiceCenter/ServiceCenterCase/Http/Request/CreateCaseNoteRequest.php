<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Auth\User;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property ServiceCenterCase $case
 */
class CreateCaseNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['required'],
        ];
    }

    public function getNote(): string
    {
        return $this->input('note');
    }

    public function getAgent(): User
    {
        return $this->user();
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }
}
