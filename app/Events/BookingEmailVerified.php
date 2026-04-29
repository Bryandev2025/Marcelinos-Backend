<?php

namespace App\Events;

use App\Broadcasting\BroadcastChannelNames;
use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingEmailVerified implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(
            BroadcastChannelNames::booking($this->booking->reference_number)
        );
    }

    public function broadcastAs(): string
    {
        return 'email.verified';
    }

    public function broadcastWith(): array
    {
        return [
            'reference_number' => $this->booking->reference_number,
            'email_verified_at' => $this->booking->email_verified_at,
        ];
    }
}
