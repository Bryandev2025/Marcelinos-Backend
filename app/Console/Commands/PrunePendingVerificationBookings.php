<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class PrunePendingVerificationBookings extends Command
{
    protected $signature = 'bookings:prune-pending-verification';

    protected $description = 'Soft-delete bookings that were never email-verified past the configured retention window';

    public function handle(): int
    {
        $hours = max(1, (int) config('booking.pending_verification_prune_hours', 48));
        $cutoff = now()->subHours($hours);

        $query = Booking::query()
            ->where('booking_status', Booking::BOOKING_STATUS_PENDING_VERIFICATION)
            ->where('created_at', '<', $cutoff);

        $count = (clone $query)->count();
        $query->each(fn (Booking $booking) => $booking->delete());

        if ($count > 0) {
            $this->info("Soft-deleted {$count} pending verification booking(s) older than {$hours} hour(s).");
        } else {
            $this->comment('No stale pending verification bookings to prune.');
        }

        return self::SUCCESS;
    }
}
