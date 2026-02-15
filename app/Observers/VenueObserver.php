<?php

namespace App\Observers;

use App\Events\VenuesUpdated;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates API cache and broadcasts so frontend (homepage) stays up to date.
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
