<?php

use App\Http\Web\Admin\Controllers\BookingController;
use App\Http\Web\Admin\Controllers\PlanningController;
use App\Http\Web\Admin\Controllers\CouponController;
use App\Http\Web\Admin\Controllers\ProductController;
use App\Http\Web\Admin\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::group(
    [
        'prefix' => 'admin',
        'as' => 'admin.',
    ],
    static function () {
        Route::get('bookings', [BookingController::class, 'index'])
            ->name('bookings.index');

        Route::get('bookings/{booking}', [BookingController::class, 'show'])
            ->name('bookings.show');
    }
);
