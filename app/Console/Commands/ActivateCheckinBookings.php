<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ActivateCheckinBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:activate-checkins
                            {--date= : The date (Y-m-d) to process; defaults to today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark bookings with check-in date today and status paid as occupied';

    /**
     * Execute the console command.
     * Uses Eloquent so Booking model events run.
     */
    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        $bookings = Booking::query()
            ->whereDate('check_in', $date)
            ->where('status', Booking::STATUS_PAID)
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $booking->update(['status' => Booking::STATUS_OCCUPIED]);
            $count++;
        }

        if ($count > 0) {
            $this->info("Marked {$count} booking(s) as occupied for check-in date {$date}.");
        } else {
            $this->comment("No paid bookings with check-in on {$date}.");
        }

        return self::SUCCESS;
    }
}
