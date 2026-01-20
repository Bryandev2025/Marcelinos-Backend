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

Route::apiResource('venues', VenueController::class);

//Room
Route::get('rooms', [RoomController::class, 'index']);
Route::get('/rooms/{id}', [RoomController::class, 'show']);

//Venue
Route::get('/venues', [VenueController::class, 'index']);
Route::get('/venues/{id}', [VenueController::class, 'show']);