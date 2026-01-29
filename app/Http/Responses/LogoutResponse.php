<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->to('/login');
    }
}
