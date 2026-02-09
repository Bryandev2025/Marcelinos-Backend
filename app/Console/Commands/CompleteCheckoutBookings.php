<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CompleteCheckoutBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:complete-checkouts
                            {--date= : The date (Y-m-d) to process; defaults to today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark bookings with check-out date today and status occupied as complete (frees rooms)';

    /**
     * Execute the console command.
     * Uses Eloquent so Booking model events run and room status is updated.
     */
    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        $bookings = Booking::query()
            ->whereDate('check_out', $date)
            ->where('status', Booking::STATUS_OCCUPIED)
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $booking->update(['status' => Booking::STATUS_COMPLETED]);
            $count++;
        }

        if ($count > 0) {
            $this->info("Marked {$count} booking(s) as complete for check-out date {$date}.");
        } else {
            $this->comment("No occupied bookings with check-out on {$date}.");
        }

        return self::SUCCESS;
    }
}
