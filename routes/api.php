<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\BookingController;
use Illuminate\App\Http\Controllers\VenueController;
use Illuminate\App\Http\Controllers\RoomsController;
use Illuminate\App\Http\Controllers\ImagesController;
use Illuminate\App\Http\Controllers\GuestController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



// Guests can create a booking
Route::post('/bookings', [BookingController::class, 'store']);
