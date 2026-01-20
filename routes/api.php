<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Bookings;
use App\Http\Controllers\API\Rooms;
use App\Http\Controllers\API\Guests;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('bookings', [Bookings::class, 'index']);
Route::post('bookings', [Bookings::class, 'store']);
Route::get('bookings/{id}', [Bookings::class, 'show']);
Route::put('bookings/{id}', [Bookings::class, 'update']);
Route::delete('bookings/{id}', [Bookings::class, 'destroy']);
Route::patch('bookings/{booking}/cancel', [Bookings::class, 'cancel']);


Route::apiResource('rooms', Rooms::class);
Route::apiResource('guests', Guests::class);

Route::get('/booking-receipt/{reference}', [Bookings::class, 'showByReference']);
