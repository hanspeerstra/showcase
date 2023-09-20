<?php
$currentRouteName = \Illuminate\Support\Facades\Route::currentRouteName();
?>

<div class="w-screen p-4 flex items-center">
    <div class="bg-gray-800 rounded px-4 w-full flex items-baseline" role="navigation">
        <a
            href="{{ route('admin.bookings.index') }}"
            class="{{ $currentRouteName === 'admin.bookings.index' ? 'bg-black text-white' : 'text-gray-300 hover:text-white' }} px-4 py-3"
            aria-current="page"
        >
            Boekingen
        </a>
    </div>
</div>
