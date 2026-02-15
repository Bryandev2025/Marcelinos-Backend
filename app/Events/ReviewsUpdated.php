<?php

namespace App\Events;

use App\Broadcasting\BroadcastChannelNames;
use Illuminate\Broadcasting\Channel;

/** Public event so frontend can refresh reviews/testimonials (landing page). */
final class ReviewsUpdated extends BaseBroadcastEvent
{
    public function broadcastOn(): array
    {
        return [new Channel(BroadcastChannelNames::reviews())];
    }

    public function broadcastWith(): array
    {
        return ['updated_at' => now()->toIso8601String()];
    }
}
