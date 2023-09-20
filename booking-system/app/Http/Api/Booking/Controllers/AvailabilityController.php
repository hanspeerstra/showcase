<?php

namespace App\Http\Api\Booking\Controllers;

use App\Http\Api\Booking\Requests\GetAvailabilityRequest;
use App\Http\Api\Booking\Resources\AvailabilityResource;
use Domains\Booking\Services\AvailabilityService;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilityController
{
    public function index(
        GetAvailabilityRequest $request,
        AvailabilityService $productAvailabilityService
    ): JsonResource {
        $availability = $productAvailabilityService->getAvailability(
            $request->getArrivalDate(),
            $request->getDepartureDate()
        );

        return new AvailabilityResource($availability);
    }
}
