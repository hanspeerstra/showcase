<?php

namespace App\Http\Api\Booking\Controllers;

use App\Http\Api\Booking\Requests\StoreBookingRequest;
use App\Http\Controller;
use Domains\Booking\CreateBookingData;
use Domains\Booking\Services\BookingService;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function store(
        StoreBookingRequest $request,
        BookingService $bookingService
    ): JsonResponse {
        $bookingService->create(
            new CreateBookingData(
                $request->getArrivalDate(),
                $request->getDepartureDate(),
                $request->getGuests(),
                $request->getAdditionalGuests()
            )
        );

        return new JsonResponse();
    }
}
