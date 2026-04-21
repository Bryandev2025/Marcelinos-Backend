<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Notifications\Slack\BookingLifecycleSlackNotification;
use App\Support\SlackBookingAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AlertUnsettledStays extends Command
{
    protected $signature = 'bookings:alert-unsettled-stays {--at= : Optional evaluation time (defaults to now)}';

    protected $description = 'Alert when occupied bookings still have unpaid balance after the 9:00 PM Manila check-in-day settlement deadline.';

    public function handle(): int
    {
        $at = $this->option('at')
            ? Carbon::parse($this->option('at'))
            : now();

        $tz = Booking::timezoneManila();
        $nowManila = $at->copy()->timezone($tz);

        $count = 0;

        Booking::query()
            ->where('stay_status', Booking::STAY_STATUS_OCCUPIED)
            ->whereIn('payment_status', [Booking::PAYMENT_STATUS_UNPAID, Booking::PAYMENT_STATUS_PARTIAL])
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$count, $nowManila, $tz): void {
                foreach ($bookings as $booking) {
                    $deadline = $booking->checkInDayUnpaidSettlementDeadlineManila();
                    if (! $deadline) {
                        continue;
                    }

                    // Only alert at/after settlement deadline on check-in day (Manila time).
                    if ($nowManila->lt($deadline)) {
                        continue;
                    }

                    $cacheKey = 'booking.unsettled_stay.alerted.'.(int) $booking->id.'.'.$deadline->toDateString();
                    if (! Cache::add($cacheKey, true, now()->addHours(36))) {
                        continue;
                    }

                    SlackBookingAlerts::notify(new BookingLifecycleSlackNotification($booking, 'unsettled_stay'));
                    $count++;
                }
            });

        if ($count > 0) {
            $this->info("Alerted {$count} unsettled occupied booking(s) (evaluated at {$nowManila->toIso8601String()}).");
        } else {
            $this->comment('No unsettled occupied bookings matched the alert rule at this run.');
        }

        return self::SUCCESS;
    }
}

