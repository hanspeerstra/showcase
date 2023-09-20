<?php

namespace App\Http\Api\Booking\Requests;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Http\FormRequest;

abstract class AbstractChangeoverDaysRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'startDate' => [
                'required',
                'date_format:Y-m-d',
            ],
            'endDate' => [
                'required',
                'date_format:Y-m-d',
            ],
        ];
    }

    public function getStartDate(): DateTimeInterface
    {
        return DateTimeImmutable::createFromFormat('!Y-m-d', $this->input('startDate'));
    }

    public function getEndDate(): DateTimeInterface
    {
        return DateTimeImmutable::createFromFormat('!Y-m-d', $this->input('endDate'));
    }
}
