<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

if ($adminPanel = Filament::getPanel('admin')) {
    $loginMiddleware = array_merge($adminPanel->getMiddleware(), ['guest']);

    Route::middleware($loginMiddleware)->group(function () use ($adminPanel): void {
        Route::get('/login', $adminPanel->getLoginRouteAction())
            ->name('filament.admin.auth.login');
    });
}
