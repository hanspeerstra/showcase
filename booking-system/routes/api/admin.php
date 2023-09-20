<?php

use App\Http\Api\Admin\Controllers\BookingController;
use App\Http\Api\Admin\Controllers\BulkBookingController;
use Illuminate\Support\Facades\Route;

Route::get('bookings', [BookingController::class, 'index']);
Route::delete('bookings/bulk', [BulkBookingController::class, 'destroy']);
Route::post('bookings/bulk/erase-personal-data', [BulkBookingController::class, 'erasePersonalData']);
