<?php

namespace App\Http\Api\Booking\Controllers;

use App\Http\Api\Admin\Planning\Resources\ChangeoverDayResource;
use App\Http\Api\Booking\Requests\GetCheckInDaysRequest;
use Domains\Booking\Services\ChangeoverDayService;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckInDayController
{
    public function index(
        GetCheckInDaysRequest $request,
        ChangeoverDayService $changeoverDayService
    ): JsonResource {
        return ChangeoverDayResource::collection(
            $changeoverDayService->getCheckInDaysWithinInterval(
                $request->getStartDate(),
                $request->getEndDate()
            )
        );
    }
}
