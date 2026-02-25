<?php

namespace App\Observers;

use App\Events\VenuesUpdated;
use App\Models\Venue;

/**
 * Broadcasts so frontend stays up to date in real time.
 * Fires on create, update, and delete so the client refetches venues (Step1, homepage).
 */
class VenueObserver
{
    public function saved(Venue $venue): void
    {
        VenuesUpdated::dispatch();
    }

    public function deleted(Venue $venue): void
    {
        VenuesUpdated::dispatch();
    }
}
