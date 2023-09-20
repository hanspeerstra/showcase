<?php

namespace App\Http\Api\Booking\Requests;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Http\FormRequest;

class GetAvailabilityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'arrivalDate' => [
                'required',
                'date_format:Y-m-d',
            ],
            'departureDate' => [
                'required',
                'date_format:Y-m-d',
            ],
        ];
    }

    public function getArrivalDate(): DateTimeInterface
    {
        return DateTimeImmutable::createFromFormat('!Y-m-d', $this->input('arrivalDate'));
    }

    public function getDepartureDate(): DateTimeInterface
    {
        return DateTimeImmutable::createFromFormat('!Y-m-d', $this->input('departureDate'));
    }
}
