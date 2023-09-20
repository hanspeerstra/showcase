<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseSchedule\Http\Request;

use App\ServiceCenter\CaseSchedule\Service\CaseScheduleOverviewService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CaseScheduleOverviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1'],
            'filters' => ['nullable', 'array'],
            'filters.postalCode' => ['sometimes'],
            'filters.caseNumber' => ['sometimes', 'integer'],
            'sorting' => ['nullable', 'array'],
            'sorting.*' => ['string', Rule::in(['ASC', 'DESC'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $sortableColumns = array_keys(CaseScheduleOverviewService::$sortableFieldMap);

        $validator->setCustomMessages([
            'field_not_sortable' => 'Field not sortable, allowed fields: ' . implode(', ', $sortableColumns),
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

    public function getSorting(): array
    {
        return $this->input('sorting', []);
    }

    public function getPostalCode(): ?string
    {
        return $this->input('filters.postalCode');
    }

    public function getCaseNumber(): ?int
    {
        $caseNumber = $this->input('filters.caseNumber');

        if (null !== $caseNumber) {
            return (int) $caseNumber;
        }

        return null;
    }
}
