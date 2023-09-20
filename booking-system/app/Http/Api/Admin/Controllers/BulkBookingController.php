<?php

namespace App\Http\Api\Admin\Controllers;

use App\Http\Api\Admin\Requests\BulkDeleteBookingRequest;
use App\Http\Api\Admin\Requests\BulkErasePersonalDataRequest;
use App\Http\Controller;
use Domains\Booking\Repositories\BookingRepository;
use Domains\Booking\Services\BulkBookingService;
use Illuminate\Http\JsonResponse;

class BulkBookingController extends Controller
{
    public function destroy(
        BulkDeleteBookingRequest $request,
        BulkBookingService $bulkBookingService,
        BookingRepository $bookingRepository
    ): JsonResponse {
        $bookings = $bookingRepository->getByIdList(...$request->getBookingIdList());

        $bulkBookingService->delete(...$bookings);

        return new JsonResponse();
    }

    public function erasePersonalData(
        BulkErasePersonalDataRequest $request,
        BulkBookingService $bulkBookingService,
        BookingRepository $bookingRepository
    ): JsonResponse {
        $bookings = $bookingRepository->getByIdList(...$request->getBookingIdList());

        $bulkBookingService->erasePersonalData(...$bookings);

        return new JsonResponse();
    }
}
