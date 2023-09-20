<?php

namespace App\Http\Api\Booking\Requests;

use DateTimeImmutable;
use DateTimeInterface;
use Domains\Booking\GuestData;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'arrivalDate' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'departureDate' => [
                'required',
                'date_format:Y-m-d',
            ],
            'guests' => [
                'required',
                'array'
            ],
            'guests.*.name' => [
                'required'
            ],
            'extraGuests' => ['required', 'integer'],
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

    /**
     * @return GuestData[]
     */
    public function getGuests(): array
    {
        return [];
    }

    public function getAdditionalGuests(): int
    {
        return $this->integer('extraGuests');
    }
}
