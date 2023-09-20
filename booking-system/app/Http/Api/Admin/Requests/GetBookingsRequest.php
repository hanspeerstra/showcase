<?php

namespace App\Http\Api\Admin\Requests;

use Domains\Booking\BookingFinderFilters;
use Domains\Shared\Sort;

class GetBookingsRequest extends AbstractPaginatedRequest
{
    public function rules(): array
    {
        return array_merge(
            parent::rules(),
            [
                'search' => ['sometimes', 'nullable', 'string'],
                'filters.product' => ['sometimes', 'integer'],
                'filters.fiscalYear' => ['sometimes', 'integer'],
                'filters.trashed' => ['sometimes', 'boolean'],
                'sort' => ['sometimes']
            ]
        );
    }

    public function getSearch(): ?string
    {
        return $this->input('search');
    }

    public function getFilters(): BookingFinderFilters
    {
        return new BookingFinderFilters(
            $this->getProduct(),
            $this->getFiscalYear(),
            $this->notInTrash()
        );
    }

    public function getSorting(): ?Sort
    {
        if ($this->isNotFilled('sort')) {
            return null;
        }

        return Sort::from($this->input('sort'));
    }

    private function getProduct(): ?int
    {
        if ($this->isNotFilled('filters.product')) {
            return null;
        }

        return $this->integer('filters.product');
    }

    private function getFiscalYear(): ?int
    {
        if ($this->isNotFilled('filters.fiscalYear')) {
            return null;
        }

        return $this->integer('filters.fiscalYear');
    }

    private function notInTrash(): bool
    {
        return !$this->inTrash();
    }

    private function inTrash(): bool
    {
        return $this->boolean('filters.inTrash');
    }
}
