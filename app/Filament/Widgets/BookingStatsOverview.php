<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Booking;
use Carbon\Carbon;

class BookingStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();

        return [
            Stat::make('New Bookings', Booking::whereDate('created_at', $today)->count())
                ->description('Bookings created today')
                ->icon('heroicon-o-plus-circle')
                ->color('success'),

            Stat::make('Total Revenue', Booking::sum('total_price'))
                ->description('Total revenue from all bookings')
                ->icon('heroicon-o-currency-dollar')
                ->color('amber'),

            Stat::make('Total Reserved', Booking::where('status', 'reserved')->count())
                ->description('Bookings currently reserved')
                ->icon('heroicon-o-clock')
                ->color('warning'),
        ];
    }
}
