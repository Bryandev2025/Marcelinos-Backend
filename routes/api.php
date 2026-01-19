<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\App\Http\Controllers\VenueController;
use Illuminate\App\Http\Controllers\RoomsController;
use Illuminate\App\Http\Controllers\BookingsController;
use Illuminate\App\Http\Controllers\ImagesController;
use Illuminate\App\Http\Controllers\GuestController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('venues', VenueController::class);