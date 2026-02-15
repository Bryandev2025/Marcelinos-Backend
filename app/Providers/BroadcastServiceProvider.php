<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     * Registers broadcast auth route and channel authorization logic.
     */
    public function boot(): void
    {
        // SPA/API: use Sanctum so frontend can authenticate with Bearer token
        Broadcast::routes(['middleware' => ['api', 'auth:sanctum']]);

        require base_path('routes/channels.php');
    }
}
