<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// routes/web.php temporarily
use App\Models\Venue;

Route::get('/test-venue-images', function () {
    $venue = Venue::with(['mainImage', 'gallery'])->find(2); // use any venue ID
    dd([
        'mainImage' => $venue->mainImage?->url,
        'galleryImages' => $venue->gallery->pluck('url')->all()
    ]);
});