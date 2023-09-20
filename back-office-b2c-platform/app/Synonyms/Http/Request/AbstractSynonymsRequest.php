<?php

declare(strict_types=1);

namespace App\Synonyms\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

abstract class AbstractSynonymsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'synonyms' => ['array'],
            'synonyms.*.synonym' => ['required', 'string'],
        ];
    }

    /**
     * @return string[]
     */
    public function getSynonyms(): array
    {
        $synonyms = [];
        foreach ($this->input('synonyms') as $index => $synonymInput) {
            $synonyms[] = $this->input('synonyms.' . $index . '.synonym');
        }

        return $synonyms;
    }

    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->input('synonyms') as $index => $synonymInput) {
            $attributes['synonyms.' . $index . '.synonym'] = 'Synoniem';
        }

        return $attributes;
    }
}
