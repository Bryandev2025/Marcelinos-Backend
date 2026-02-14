<?php

namespace App\Observers;

use App\Models\Venue;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates API response cache when venue data changes.
 */
class VenueObserver
{
    public function saved(Venue $venue): void
    {
        $this->invalidateVenueCache($venue);
    }

    public function deleted(Venue $venue): void
    {
        $this->invalidateVenueCache($venue);
    }

    private function invalidateVenueCache(Venue $venue): void
    {
        Cache::forget("api.venues.show.{$venue->id}");
        Cache::forget('api.venues.list.all');
    }
}
