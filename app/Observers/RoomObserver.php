<?php

namespace App\Observers;

use App\Events\RoomsUpdated;
use App\Models\Room;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates API cache and broadcasts so frontend stays up to date in real time.
 * Fires on create, update, and delete so the client refetches rooms (Step1, homepage).
 */
class RoomObserver
{
    public function saved(Room $room): void
    {
        $this->invalidateRoomCache($room);
        RoomsUpdated::dispatch();
    }

    public function deleted(Room $room): void
    {
        $this->invalidateRoomCache($room);
        RoomsUpdated::dispatch();
    }

    private function invalidateRoomCache(Room $room): void
    {
        Cache::forget("api.rooms.show.{$room->id}");
        Cache::forget('api.rooms.list.all');
    }
}
