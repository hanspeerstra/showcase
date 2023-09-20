<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Request;

use App\Auth\User;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseNote;
use Arcanedev\Support\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * @property ServiceCenterCase $case
 * @property ServiceCenterCaseNote $caseNote
 */
class EditCaseNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['required'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->getCase()->getId() !== $this->getCaseNote()->getCase()->getId()) {
                $validator->errors()
                    ->add('caseNote', 'Note does not belong to case');
            }
        });
    }

    public function authorize(): bool
    {
        return $this->getAgent()->can('update', $this->getCaseNote());
    }

    public function getNote(): string
    {
        return $this->input('note');
    }

    private function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    public function getCaseNote(): ServiceCenterCaseNote
    {
        return $this->caseNote;
    }

    public function getAgent(): User
    {
        return $this->user();
    }
}
