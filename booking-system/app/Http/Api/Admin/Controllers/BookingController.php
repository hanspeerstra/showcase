<?php

namespace App\Http\Api\Admin\Controllers;

use App\Http\Api\Admin\Requests\AddPaymentRequest;
use App\Http\Api\Admin\Requests\GetBookingsRequest;
use App\Http\Api\Admin\Requests\PreviewPaymentEmailRequest;
use App\Http\Api\Admin\Requests\UpdateBookingRequest;
use App\Http\Api\Admin\Requests\UpdateNotesRequest;
use App\Http\Controller;
use App\Http\Api\Admin\Resources\BookingResource;
use Domains\Booking\Models\Booking;
use Domains\Booking\UpdateBookingData;
use Domains\Booking\Services\BookingService;
use Domains\Booking\EmailService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingController extends Controller
{
    public function index(
        GetBookingsRequest $request,
        BookingService $bookingService
    ): JsonResource {
        $result = $bookingService->find(
            $request->getPage(),
            $request->getPerPage(),
            $request->getSearch(),
            $request->getFilters(),
            $request->getSorting()
        );

        (new Collection($result->getItems()))
            ->loadMissing(
                [
                    'items.product',
                    'address.country',
                    'customer',
                    'fishers',
                    'coupon'
                ]
            );

        return BookingResource::collection($result->getItems())
            ->additional(
                [
                    'page' => $result->getPage(),
                    'perPage' => $result->getPerPage(),
                    'total' => $result->getTotal(),
                ]
            );
    }
}
