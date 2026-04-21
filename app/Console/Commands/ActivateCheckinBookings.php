<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
    protected $description = 'Mark bookings with check-in date today, payment paid/partial, stay reserved, as occupied';

    /**
     * Execute the console command.
     * Uses Eloquent so Booking model events run.
     */
    public function handle(): int
    {
        try {
            $date = $this->option('date')
                ? Carbon::parse($this->option('date'))->toDateString()
                : Carbon::today()->toDateString();
        } catch (\Throwable $e) {
            $this->error('Invalid --date value. Use format Y-m-d.');

            return self::FAILURE;
        }

        $bookings = Booking::query()
            ->whereDate('check_in', $date)
            ->where('booking_status', Booking::BOOKING_STATUS_RESERVED)
            ->whereIn('payment_status', [Booking::PAYMENT_STATUS_PAID, Booking::PAYMENT_STATUS_PARTIAL])
            ->with(['roomLines', 'venues', 'rooms.bedSpecifications'])
            ->get();

        $count = 0;
        $skipped = 0;
        foreach ($bookings as $booking) {
            try {
                $booking->assertAssignmentsSatisfiedForOccupied();
            } catch (ValidationException $e) {
                $skipped++;
                $reason = collect($e->errors())->flatten()->first() ?? $e->getMessage();
                Log::warning('Skipped auto check-in: assignments incomplete', [
                    'reference_number' => $booking->reference_number,
                    'booking_id' => $booking->id,
                    'reason' => $reason,
                ]);
                $this->warn("Skipped {$booking->reference_number}: {$reason}");

                continue;
            }
            $booking->update(['booking_status' => Booking::BOOKING_STATUS_OCCUPIED]);
            $count++;
        }

        if ($count > 0) {
            $this->info("Marked {$count} booking(s) as occupied for check-in date {$date}.");
        } elseif ($bookings->isEmpty()) {
            $this->comment("No eligible reserved + paid/partial bookings with check-in on {$date}.");
        }
        if ($skipped > 0) {
            $this->comment("Skipped {$skipped} booking(s) with incomplete room/venue assignments.");
        }

        return self::SUCCESS;
    }
}
