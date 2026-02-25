<?php

namespace App\Observers;

use App\Events\RoomsUpdated;
use App\Models\Room;

/**
 * Broadcasts so frontend stays up to date in real time.
 * Fires on create, update, and delete so the client refetches rooms (Step1, homepage).
 */
class RoomObserver
{
    public function saved(Room $room): void
    {
        RoomsUpdated::dispatch();
    }

    public function deleted(Room $room): void
    {
        RoomsUpdated::dispatch();
    }
}
