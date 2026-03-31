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
                            {--days=3 : Auto-cancel unpaid bookings older than this many days}
                            {--before= : Optional cutoff datetime; defaults to now}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-cancel unpaid bookings that exceed the unpaid time window';

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
        $cutoff = $before->copy()->subDays($days);

        $bookings = Booking::query()
            ->where('status', Booking::STATUS_UNPAID)
            ->where('created_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            if ($booking->expireIfUnpaidExceededRule($before, $days)) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->info("Cancelled {$count} unpaid booking(s) older than {$days} day(s).");
        } else {
            $this->comment("No unpaid bookings older than {$days} day(s).");
        }

        return self::SUCCESS;
    }
}
