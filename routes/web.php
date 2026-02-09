<?php

use App\Models\Booking;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Signed link from testimonial email: redirects to client app testimonial form.
Route::get('/testimonial/feedback/{booking}', function (Booking $booking) {
    $base = rtrim(config('app.frontend_url'), '/');
    return redirect($base . '/testimonial?booking=' . $booking->id);
})->name('testimonial.feedback.redirect')->middleware('signed');

if ($adminPanel = Filament::getPanel('admin')) {
    $loginMiddleware = array_merge($adminPanel->getMiddleware(), ['guest']);

    Route::middleware($loginMiddleware)->group(function () use ($adminPanel): void {
        Route::get('/login', $adminPanel->getLoginRouteAction())
            ->name('filament.admin.auth.login');
    });
}
