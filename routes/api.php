<?php

use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\BlockedDateController;
use App\Http\Controllers\API\ContactController;
use App\Http\Controllers\API\GalleryController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\GuestController;
use App\Http\Controllers\API\RoomController;
use App\Http\Controllers\API\VenueController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;




Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('bookings', [BookingController::class, 'index']);
Route::post('bookings', [BookingController::class, 'store']);
Route::get('bookings/{id}', [BookingController::class, 'show']);
Route::put('bookings/{id}', [BookingController::class, 'update']);
Route::delete('bookings/{id}', [BookingController::class, 'destroy']);
Route::patch('bookings/{booking}/cancel', [BookingController::class, 'cancel']);
Route::get('bookings/reference/{reference}', [BookingController::class, 'showByReferenceNumber']);
Route::post('bookings/reference/{reference}/review', [ReviewController::class, 'storeByBookingReference']);

Route::apiResource('/bookings/store', BookingController::class);

Route::get('/booking-receipt/{reference}', [BookingController::class, 'showByReference']);

Route::apiResource('venues', VenueController::class);

//Room
Route::get('rooms', [RoomController::class, 'index']);
Route::get('/rooms/{id}', [RoomController::class, 'show']);

//Venue
Route::get('/venues', [VenueController::class, 'index']);
Route::get('/venues/{id}', [VenueController::class, 'show']);

// Blocked Dates
Route::get('/blocked-dates', [BlockedDateController::class, 'index']);

// Contact form (public)
Route::post('/contact', [ContactController::class, 'store']);

// Gallery
Route::get('/galleries', [GalleryController::class, 'index']);
Route::get('/galleries/{id}', [GalleryController::class, 'show']);