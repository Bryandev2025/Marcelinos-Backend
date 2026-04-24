<?php

namespace App\Events;

use App\Broadcasting\BroadcastChannelNames;
use App\Models\Booking;
use Illuminate\Broadcasting\Channel;

/**
 * Broadcast when a booking's status (or relevant data) changes.
 * Public channel so guests on the receipt page can listen without auth.
 * Listen on channel "booking.{receipt_token}" with event "BookingStatusUpdated".
 */
final class BookingStatusUpdated extends BaseBroadcastEvent
{
    public function __construct(
        public Booking $booking
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel(
                BroadcastChannelNames::booking((string) ($this->booking->receipt_token ?: $this->booking->reference_number))
            ),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'reference' => $this->booking->reference_number,
            'booking_status' => $this->booking->booking_status,
            'payment_status' => $this->booking->payment_status,
            'check_in' => $this->booking->check_in?->toIso8601String(),
            'check_out' => $this->booking->check_out?->toIso8601String(),
            'updated_at' => $this->booking->updated_at?->toIso8601String(),
        ];
    }
}
