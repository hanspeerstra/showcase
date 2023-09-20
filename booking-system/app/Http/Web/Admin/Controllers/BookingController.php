<?php

namespace App\Http\Web\Admin\Controllers;

use App\Http\Controller;
use Domains\Booking\Models\Booking;
use Illuminate\Contracts\View\View;

class BookingController extends Controller
{
    public function index(): View
    {
        return view('admin.bookings.index');
    }

    public function show(Booking $booking): View
    {
        return view(
            'admin.bookings.show',
            [
                'booking' => $booking
            ]
        );
    }
}
