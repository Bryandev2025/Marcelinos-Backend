<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\RoomChecklistItem;
use App\Models\User;

final class BookingDamageSettlement
{
    public static function syncFromChecklist(Booking $booking, ?User $actor = null): void
    {
        $booking->loadMissing('roomChecklists.items');

        $hasClaim = $booking->roomChecklists
            ->flatMap(fn ($checklist) => $checklist->items)
            ->contains(fn (RoomChecklistItem $item): bool => in_array((string) $item->status, [
                RoomChecklistItem::STATUS_BROKEN,
                RoomChecklistItem::STATUS_MISSING,
            ], true));

        if ($hasClaim) {
            $booking->update([
                'has_damage_claim' => true,
                'damage_settlement_status' => Booking::DAMAGE_SETTLEMENT_STATUS_PENDING,
                'damage_settlement_marked_by' => $actor?->id,
                'damage_settlement_marked_at' => now(),
            ]);

            return;
        }

        $booking->update([
            'has_damage_claim' => false,
            'damage_settlement_status' => Booking::DAMAGE_SETTLEMENT_STATUS_NONE,
            'damage_settlement_notes' => null,
            'damage_settlement_marked_by' => null,
            'damage_settlement_marked_at' => null,
        ]);
    }

    public static function markSettled(Booking $booking, ?string $notes, ?User $actor = null): void
    {
        $booking->update([
            'has_damage_claim' => true,
            'damage_settlement_status' => Booking::DAMAGE_SETTLEMENT_STATUS_SETTLED,
            'damage_settlement_notes' => filled($notes) ? trim((string) $notes) : null,
            'damage_settlement_marked_by' => $actor?->id,
            'damage_settlement_marked_at' => now(),
        ]);
    }
}
