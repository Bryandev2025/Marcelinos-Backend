<?php

use App\Models\ActivityLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Booking scheduled tasks (Asia/Manila)
|--------------------------------------------------------------------------
| Server cron (every minute): php artisan schedule:run
*/

$manila = 'Asia/Manila';

/**
 * Complete check-outs: occupied → completed when check_out has passed.
 * Runs every 10 minutes 10:00–10:50 and once at 11:00. Testimonial feedback email is sent
 * immediately when status becomes completed (see Booking model updated hook).
 */
Schedule::command('bookings:complete-checkouts')
    ->cron('0,10,20,30,40,50 10 * * *')
    ->timezone($manila)
    ->withoutOverlapping();

Schedule::command('bookings:complete-checkouts')
    ->dailyAt('11:00')
    ->timezone($manila)
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Daily at 12:00 — Manila
|--------------------------------------------------------------------------
| bookings:activate-checkins  — paid/partial with check-in today → occupied
| bookings:send-reminders     — reminder email one day before check-in
|
| bookings:cancel-unpaid is scheduled separately every 15 minutes (9:00 PM Manila check-in-day rule).
*/
foreach ([
    'bookings:activate-checkins' => true,
    'bookings:send-reminders' => true,
] as $signature => $withoutOverlapping) {
    $event = Schedule::command($signature)
        ->dailyAt('12:00')
        ->timezone($manila);
    if ($withoutOverlapping) {
        $event->withoutOverlapping();
    }
}

/*
|--------------------------------------------------------------------------
| Every 15 minutes — Manila
|--------------------------------------------------------------------------
| Enforce unpaid settlement deadline (9:00 PM on check-in day, Manila) so cancellations run soon after due.
*/
Schedule::command('bookings:cancel-unpaid')
    ->everyFifteenMinutes()
    ->timezone($manila)
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Weekly activity-log retention cleanup
|--------------------------------------------------------------------------
| Runs every 7 days and keeps only the latest 7 days of audit records.
*/
Schedule::call(function (): void {
    ActivityLog::query()
        ->where('created_at', '<', now()->subDays(7))
        ->delete();
})
    ->name('activity-log-retention-cleanup')
    ->weekly()
    ->sundays()
    ->at('01:00')
    ->timezone($manila)
    ->withoutOverlapping();
