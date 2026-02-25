<?php

namespace App\Observers;

use App\Events\BlockedDatesUpdated;
use App\Models\BlockedDate;

/**
 * Broadcasts so frontend stays up to date when blocked dates change.
 */
class BlockedDateObserver
{
    public function saved(BlockedDate $blockedDate): void
    {
        BlockedDatesUpdated::dispatch();
    }

    public function deleted(BlockedDate $blockedDate): void
    {
        BlockedDatesUpdated::dispatch();
    }
}
