<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\RoomChecklistItem;
use App\Models\User;
use App\Support\BookingDamageSettlement;

class RoomChecklistItemObserver
{
    public function created(RoomChecklistItem $item): void
    {
        $this->syncBookingSettlement($item);
    }

    public function updated(RoomChecklistItem $item): void
    {
        $this->syncBookingSettlement($item);
    }

    public function deleted(RoomChecklistItem $item): void
    {
        $this->syncBookingSettlement($item);
    }

    private function syncBookingSettlement(RoomChecklistItem $item): void
    {
        $item->loadMissing('roomChecklist.booking');
        $booking = $item->roomChecklist?->booking;
        if (! $booking instanceof Booking) {
            return;
        }

        if ((string) $booking->booking_status !== Booking::BOOKING_STATUS_COMPLETED) {
            return;
        }

        $actor = auth()->user();
        BookingDamageSettlement::syncFromChecklist(
            $booking->fresh(['roomChecklists.items']),
            $actor instanceof User ? $actor : null,
        );
    }
}
