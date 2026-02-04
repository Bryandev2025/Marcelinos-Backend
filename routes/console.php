<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Schedule a task to update the status of bookings that have a check-out date of today and status 'occupied' to 'complete'.
 * The task will run daily at 10:00 AM.
 */
Schedule::call(function () {
    $today = Carbon::today()->toDateString();

    DB::table('bookings')
        ->whereDate('check_out', $today)
        ->where('status', 'occupied')
        ->update([
            'status' => 'complete',
            'updated_at' => now(),
        ]);
})
->dailyAt('10:00');

/**
 * Schedule a task to update the status of bookings that have a check-in date of today and status of 'paid' to 'occupied'.
 * The task will run daily at 12:00 PM.
 */
Schedule::call(function () {
    $today = Carbon::today()->toDateString();

    DB::table('bookings')
        ->whereDate('check_in', $today)
        ->where('status', 'paid')
        ->update([
            'status' => 'occupied',
            'updated_at' => now(),
        ]);
})
->dailyAt('12:00');

/**
 * Schedule a task to run daily at 12:00 PM.
 * The task cancels pending bookings with check-in date as today.
 *
 * @param  \Closure  $callback
 * @return \Illuminate\Console\Scheduling\Event
 */
Schedule::call(function () {
    $today = Carbon::today()->toDateString();

    DB::table('bookings')
        ->whereDate('check_in', $today)
        ->where('status', 'pending')
        ->update([
            'status' => 'cancelled',
            'updated_at' => now(),
        ]);
})
->dailyAt('12:00');