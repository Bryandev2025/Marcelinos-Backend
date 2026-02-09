<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\Booking;
use App\Models\BlockedDate;
use App\Models\Room;
use App\Models\Venue;
use App\Observers\BlockedDateObserver;
use App\Observers\BookingObserver;
use App\Observers\RoomObserver;
use App\Observers\VenueObserver;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Booking::class => BookingPolicy::class,
        User::class => UserPolicy::class,
        Guest::class => GuestPolicy::class,
        Venue::class => VenuePolicy::class,
        Room::class => RoomPolicy::class,
    ];

    /**
     * Override Filament's auth responses to support a single login page.
     */
    public array $singletons = [
        LoginResponseContract::class => LoginResponse::class,
        LogoutResponseContract::class => LogoutResponse::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Booking::observe(BookingObserver::class);
        Room::observe(RoomObserver::class);
        Venue::observe(VenueObserver::class);
        BlockedDate::observe(BlockedDateObserver::class);
    }
}
