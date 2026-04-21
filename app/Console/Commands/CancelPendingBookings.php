<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CancelPendingBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:cancel-unpaid
                            {--days=3 : Unused; retained for backward compatibility}
                            {--before= : Optional evaluation time; defaults to now}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate all unpaid bookings and cancel those past 9:00 PM (Asia/Manila) on the check-in calendar day (Booking::isExpiredUnpaid)';

    /**
     * Execute the console command.
     * Uses Eloquent so Booking model events run.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $before = $this->option('before')
            ? Carbon::parse($this->option('before'))
            : now();

        $count = 0;

        Booking::query()
            ->where('stay_status', Booking::STAY_STATUS_RESERVED)
            ->where('payment_status', Booking::PAYMENT_STATUS_UNPAID)
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$count, $before, $days): void {
                foreach ($bookings as $booking) {
                    if ($booking->expireIfUnpaidExceededRule($before, $days)) {
                        $count++;
                    }
                }
            });

        if ($count > 0) {
            $this->info("Cancelled {$count} unpaid booking(s) that exceeded the unpaid policy (evaluated at {$before->toIso8601String()}).");
        } else {
            $this->comment('No unpaid bookings matched the cancel policy at this run.');
        }

        return self::SUCCESS;
    }
}
