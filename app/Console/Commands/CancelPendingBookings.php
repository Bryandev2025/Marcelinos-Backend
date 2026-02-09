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
    protected $signature = 'bookings:cancel-pending
                            {--date= : The date (Y-m-d) to process; defaults to today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel pending bookings whose check-in date is today (no payment by check-in)';

    /**
     * Execute the console command.
     * Uses Eloquent so Booking model events run and room status is freed if applicable.
     */
    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        $bookings = Booking::query()
            ->whereDate('check_in', $date)
            ->where('status', Booking::STATUS_PENDING)
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $booking->update(['status' => Booking::STATUS_CANCELLED]);
            $count++;
        }

        if ($count > 0) {
            $this->info("Cancelled {$count} pending booking(s) for check-in date {$date}.");
        } else {
            $this->comment("No pending bookings with check-in on {$date}.");
        }

        return self::SUCCESS;
    }
}
