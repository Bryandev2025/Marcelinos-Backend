<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\ActivityLog;

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
$afterCheckoutSendTestimonial = fn () => Artisan::call('testimonials:send-feedback');

/**
 * Complete check-outs: occupied → completed when check_out has passed.
 * Runs every 10 minutes 10:00–10:50 and once at 11:00. After each run, send testimonial
 * feedback for eligible completed bookings (see testimonials:send-feedback).
 */
Schedule::command('bookings:complete-checkouts')
    ->cron('0,10,20,30,40,50 10 * * *')
    ->timezone($manila)
    ->withoutOverlapping()
    ->after($afterCheckoutSendTestimonial);

Schedule::command('bookings:complete-checkouts')
    ->dailyAt('11:00')
    ->timezone($manila)
    ->withoutOverlapping()
    ->after($afterCheckoutSendTestimonial);

/*
|--------------------------------------------------------------------------
| Daily at 12:00 — Manila
|--------------------------------------------------------------------------
| bookings:activate-checkins  — paid/partial with check-in today → occupied
| bookings:send-reminders     — reminder email one day before check-in
|
| bookings:cancel-unpaid is scheduled separately every 15 minutes (see below).
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
| Enforce unpaid expiry policy so stale bookings are cancelled quickly.
*/
Schedule::command('bookings:cancel-unpaid')
    ->everyFifteenMinutes()
    ->timezone($manila)
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Daily activity-log retention cleanup
|--------------------------------------------------------------------------
| Keep only the latest 60 days of audit records.
*/
Schedule::call(function (): void {
    ActivityLog::query()
        ->where('created_at', '<', now()->subDays(60))
        ->delete();
})
    ->daily()
    ->timezone($manila)
    ->withoutOverlapping();
