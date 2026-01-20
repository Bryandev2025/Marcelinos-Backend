<?php

use App\Http\Controllers\API\BookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
<<<<<<< HEAD
use App\Http\Controllers\API\BookingController;
use Illuminate\App\Http\Controllers\VenueController;
=======
// use Illuminate\App\Http\Controllers\VenueController;
>>>>>>> a11e4de (venues)
// use Illuminate\App\Http\Controllers\API\RoomController;
use App\Http\Controllers\API\GuestController;

use App\Http\Controllers\API\RoomController;
use App\Http\Controllers\API\VenueController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

<<<<<<< HEAD
=======
Route::get('bookings', [BookingController::class, 'index']);
Route::post('bookings', [BookingController::class, 'store']);
Route::get('bookings/{id}', [BookingController::class, 'show']);
Route::put('bookings/{id}', [BookingController::class, 'update']);
Route::delete('bookings/{id}', [BookingController::class, 'destroy']);
Route::patch('bookings/{booking}/cancel', [BookingController::class, 'cancel']);

Route::apiResource('guests', GuestController::class);

Route::get('/booking-receipt/{reference}', [BookingController::class, 'showByReference']);

>>>>>>> a358b7d (modified api.php)
Route::apiResource('venues', VenueController::class);

//Room
Route::get('rooms', [RoomController::class, 'index']);
Route::get('/rooms/{id}', [RoomController::class, 'show']);
<<<<<<< HEAD
=======

//Venue
Route::get('/venues', [VenueController::class, 'index']);
Route::get('/venues/{id}', [VenueController::class, 'show']);
>>>>>>> a11e4de (venues)
