<?php

namespace App\Events;

use App\Broadcasting\BroadcastChannelNames;
use App\Models\Booking;

/**
 * Broadcast when a booking's status (or relevant data) changes.
 * Listen on channel "booking.{reference}" with event "BookingStatusUpdated".
 */
final class BookingStatusUpdated extends BaseBroadcastEvent
{
    public function __construct(
        public Booking $booking
    ) {}

    public function broadcastOn(): array
    {
        return [
            new \Illuminate\Broadcasting\PrivateChannel(
                BroadcastChannelNames::booking($this->booking->reference_number)
            ),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'reference' => $this->booking->reference_number,
            'status' => $this->booking->status,
            'check_in' => $this->booking->check_in?->toIso8601String(),
            'check_out' => $this->booking->check_out?->toIso8601String(),
            'updated_at' => $this->booking->updated_at?->toIso8601String(),
        ];
    }
}
