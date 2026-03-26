<?php

namespace App\Observers;

use App\Events\BlockedDatesUpdated;
use App\Events\VenuesUpdated;
use App\Models\VenueBlockedDate;
use Illuminate\Support\Facades\Log;
use Throwable;

class VenueBlockedDateObserver
{
    public function saved(VenueBlockedDate $venueBlockedDate): void
    {
        $this->safeBroadcast($venueBlockedDate, 'saved');
    }

    public function deleted(VenueBlockedDate $venueBlockedDate): void
    {
        $this->safeBroadcast($venueBlockedDate, 'deleted');
    }

    private function safeBroadcast(VenueBlockedDate $venueBlockedDate, string $action): void
    {
        try {
            BlockedDatesUpdated::dispatch();
        } catch (Throwable $exception) {
            Log::warning('BlockedDatesUpdated broadcast failed (venue block)', [
                'venue_blocked_date_id' => $venueBlockedDate->id,
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            VenuesUpdated::dispatch();
        } catch (Throwable $exception) {
            Log::warning('VenuesUpdated broadcast failed (venue block)', [
                'venue_blocked_date_id' => $venueBlockedDate->id,
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
