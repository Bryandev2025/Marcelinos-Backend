<?php

namespace App\Observers;

use App\Models\Room;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates API response cache when room data changes so clients get fresh data.
 */
class RoomObserver
{
    public function saved(Room $room): void
    {
        $this->invalidateRoomCache($room);
    }

    public function deleted(Room $room): void
    {
        $this->invalidateRoomCache($room);
    }

    private function invalidateRoomCache(Room $room): void
    {
        Cache::forget("api.rooms.show.{$room->id}");
        Cache::forget('api.rooms.list.all');
    }
}
