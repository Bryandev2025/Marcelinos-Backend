<?php

namespace App\Events;

use App\Broadcasting\BroadcastChannelNames;
use Illuminate\Broadcasting\Channel;

/** Public event so frontend can refresh venues (homepage). */
final class VenuesUpdated extends BaseBroadcastEvent
{
    public function broadcastOn(): array
    {
        return [new Channel(BroadcastChannelNames::venues())];
    }

    public function broadcastWith(): array
    {
        return ['updated_at' => now()->toIso8601String()];
    }
}
