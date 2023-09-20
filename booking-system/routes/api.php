<?php

use App\Http\Api\Booking\Controllers\AvailabilityController;
use App\Http\Api\Booking\Controllers\BookingController;
use App\Http\Api\Booking\Controllers\CheckInDayController;
use App\Http\Api\Booking\Controllers\CheckOutDayController;
use Illuminate\Support\Facades\Route;

Route::group(
    [],
    static function () {
        Route::get('check-in-days', [CheckInDayController::class, 'index']);
        Route::get('check-out-days', [CheckOutDayController::class, 'index']);
        Route::get('availability', [AvailabilityController::class, 'index']);
        Route::get('book', [BookingController::class, 'store']);
    }
);

Route::name('admin.')
    ->prefix('admin')
    ->group(base_path('routes/api/admin.php'));
