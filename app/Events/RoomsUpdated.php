<?php

namespace App\Events;

use App\Broadcasting\BroadcastChannelNames;
use Illuminate\Broadcasting\Channel;

/** Public event so frontend can refresh rooms (Step1 & homepage). */
final class RoomsUpdated extends BaseBroadcastEvent
{
    public function broadcastOn(): array
    {
        return [new Channel(BroadcastChannelNames::rooms())];
    }

    public function broadcastWith(): array
    {
        return ['updated_at' => now()->toIso8601String()];
    }
}
