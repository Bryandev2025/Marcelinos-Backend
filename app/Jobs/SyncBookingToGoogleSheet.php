<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\GoogleSheetsBookingSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncBookingToGoogleSheet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $bookingId,
        public string $referenceNumber,
        public bool $removeOnly = false
    ) {}

    public function handle(GoogleSheetsBookingSyncService $syncService): void
    {
        if ($this->removeOnly) {
            $syncService->removeBookingByReference($this->referenceNumber);

            return;
        }

        $booking = Booking::withTrashed()
            ->with(['guest', 'rooms', 'venues', 'roomLines'])
            ->find($this->bookingId);

        if (! $booking) {
            $syncService->removeBookingByReference($this->referenceNumber);

            return;
        }

        $syncService->syncBooking($booking);
    }
}
