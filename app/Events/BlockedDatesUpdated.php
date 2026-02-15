<?php

namespace App\Events;

use App\Broadcasting\BroadcastChannelNames;
use Illuminate\Broadcasting\Channel;

/** Public event so frontend can refresh blocked dates (calendar/booking form). */
final class BlockedDatesUpdated extends BaseBroadcastEvent
{
    public function broadcastOn(): array
    {
        return [new Channel(BroadcastChannelNames::blockedDates())];
    }

    public function broadcastWith(): array
    {
        return ['updated_at' => now()->toIso8601String()];
    }
}
