<?php

namespace App\Observers;

use App\Models\BlockedDate;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates blocked-dates API cache when blocked dates change.
 */
class BlockedDateObserver
{
    public function saved(BlockedDate $blockedDate): void
    {
        Cache::forget('api.blocked-dates');
    }

    public function deleted(BlockedDate $blockedDate): void
    {
        Cache::forget('api.blocked-dates');
    }
}
