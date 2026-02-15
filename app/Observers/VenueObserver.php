<?php

namespace App\Observers;

use App\Events\VenuesUpdated;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates API cache and broadcasts so frontend stays up to date in real time.
 * Fires on create, update, and delete so the client refetches venues (Step1, homepage).
 */
class VenueObserver
{
    public function saved(Venue $venue): void
    {
        $this->invalidateVenueCache($venue);
        VenuesUpdated::dispatch();
    }

    public function deleted(Venue $venue): void
    {
        $this->invalidateVenueCache($venue);
        VenuesUpdated::dispatch();
    }

    private function invalidateVenueCache(Venue $venue): void
    {
        Cache::forget("api.venues.show.{$venue->id}");
        Cache::forget('api.venues.list.all');
    }
}
