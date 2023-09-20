<?php

declare(strict_types=1);

namespace App\Synonyms\Overview\Http\Request;

use App\Synonyms\Overview\Model\SynonymsOverviewItem;
use App\Synonyms\Overview\Service\SynonymsOverviewService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SynonymsOverviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1'],
            'filters' => ['nullable', 'array'],
            'filters.hasAnySynonym' => ['sometimes', 'boolean'],
            'filters.typeList' => ['sometimes', 'array'],
            'filters.typeList.*' => [
                'string',
                Rule::in(SynonymsOverviewItem::TYPES),
            ],
            'filters.isMatchmaker' => ['sometimes', 'boolean'],
            'sorting' => ['nullable', 'array'],
            'sorting.*' => ['string', Rule::in(['ASC', 'DESC'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $sortableColumns = array_keys(SynonymsOverviewService::$sortableFieldMap);

        $validator->setCustomMessages([
            'field_not_sortable' => 'Field not sortable, allowed fields: ' . implode($sortableColumns),
        ]);

        $validator->after(function (Validator $validator) use ($sortableColumns) {
            if ($validator->failed()) {
                // Static rules failed
                return;
            }

            $sortingFields = array_keys($this->input('sorting', []));
            $invalidSortingFields = array_diff($sortingFields, $sortableColumns);

            if ([] !== $invalidSortingFields) {
                $validator->addFailure('sorting', 'field_not_sortable');
            }
        });
    }

    public function getPage(): int
    {
        return (int) $this->input('page', 1);
    }

    public function getPerPage(): int
    {
        return (int) $this->input('perPage', 20);
    }

    public function getSearch(): ?string
    {
        return $this->input('search');
    }

    public function getHasAnySynonymFilter(): ?bool
    {
        if ($this->input('filters.hasAnySynonym') === null) {
            return null;
        }

        return $this->boolean('filters.hasAnySynonym');
    }

    /**
     * @return string[]
     */
    public function getTypes(): array
    {
        return $this->input('filters.typeList', []);
    }

    public function getMatchmakerFilterEnabled(): bool
    {
        $filters = $this->input('filters');

        return (bool) ($filters['matchmaker'] ?? false);
    }

    public function getSorting(): array
    {
        return $this->input('sorting', []);
    }
}
