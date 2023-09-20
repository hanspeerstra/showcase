<?php

namespace App\Http\Api\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class AbstractPaginatedRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer'],
            'perPage' => ['nullable', 'integer'],
        ];
    }

    public function getPage(): int
    {
        return (int) $this->input('page', 1);
    }

    public function getPerPage(): int
    {
        return (int) $this->input('perPage', 15);
    }
}
