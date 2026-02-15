<?php

namespace App\Events;

use App\Broadcasting\BroadcastChannelNames;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Generic admin dashboard notification (e.g. new booking, new contact).
 * Listen on private channel "admin.dashboard" with event "AdminDashboardNotification".
 */
final class AdminDashboardNotification extends BaseBroadcastEvent
{
    public function __construct(
        public string $type,
        public string $title,
        public array $payload = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(BroadcastChannelNames::adminDashboard()),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'payload' => $this->payload,
            'at' => now()->toIso8601String(),
        ];
    }
}
