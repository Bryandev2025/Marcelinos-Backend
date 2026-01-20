<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// use Illuminate\App\Http\Controllers\VenueController;
// use Illuminate\App\Http\Controllers\API\RoomController;
use Illuminate\App\Http\Controllers\BookingsController;
use Illuminate\App\Http\Controllers\ImagesController;
use Illuminate\App\Http\Controllers\GuestController;

use App\Http\Controllers\API\RoomController;
use App\Http\Controllers\API\VenueController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('bookings', [Bookings::class, 'index']);
Route::post('bookings', [Bookings::class, 'store']);
Route::get('bookings/{id}', [Bookings::class, 'show']);
Route::put('bookings/{id}', [Bookings::class, 'update']);
Route::delete('bookings/{id}', [Bookings::class, 'destroy']);
Route::patch('bookings/{booking}/cancel', [Bookings::class, 'cancel']);


Route::get('/booking-receipt/{reference}', [Bookings::class, 'showByReference']);
Route::apiResource('venues', VenueController::class);

//Room
Route::get('rooms', [RoomController::class, 'index']);
Route::get('/rooms/{id}', [RoomController::class, 'show']);

//Venue
Route::get('/venues', [VenueController::class, 'index']);
Route::get('/venues/{id}', [VenueController::class, 'show']);
