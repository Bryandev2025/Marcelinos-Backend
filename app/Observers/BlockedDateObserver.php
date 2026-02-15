<?php

namespace App\Observers;

use App\Events\BlockedDatesUpdated;
use App\Models\BlockedDate;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates blocked-dates API cache and broadcasts so frontend stays up to date.
 */
class BlockedDateObserver
{
    public function saved(BlockedDate $blockedDate): void
    {
        Cache::forget('api.blocked-dates');
        BlockedDatesUpdated::dispatch();
    }

    public function deleted(BlockedDate $blockedDate): void
    {
        Cache::forget('api.blocked-dates');
        BlockedDatesUpdated::dispatch();
    }
}
