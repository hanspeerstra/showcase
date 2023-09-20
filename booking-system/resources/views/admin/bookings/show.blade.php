<?php /** @var \Domains\Booking\Models\Booking $booking */ ?>

@extends('admin.app')

@section('title', $title = sprintf('Boeking #%d', $booking->getId()))

@section('content')
    <main id="booking" data-id="{{ $booking->getId() }}"></main>
@endsection
