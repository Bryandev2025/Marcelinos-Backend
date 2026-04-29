<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomChecklist;
use App\Models\RoomChecklistItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Centralized mutations for admin booking lifecycle (used by table, calendar, record pages).
 */
final class BookingLifecycleActions
{
    /**
     * @throws \InvalidArgumentException
     */
    public static function checkIn(Booking $booking): void
    {
        $assessment = BookingCheckInEligibility::assess($booking);
        if (! $assessment['allowed']) {
            throw new \InvalidArgumentException($assessment['message'] ?? __('Cannot check in this booking.'));
        }

        $booking->update(['booking_status' => Booking::BOOKING_STATUS_OCCUPIED]);
        self::logManualLifecycleTrigger($booking, 'checkin_triggered');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function complete(Booking $booking): void
    {
        if ($booking->trashed()) {
            throw new \InvalidArgumentException(__('Cannot complete a deleted booking.'));
        }

        if (! $booking->canAdminCheckout()) {
            throw new \InvalidArgumentException(__('Booking must be occupied with at least partial payment, and checkout day must be today or later.'));
        }

        $booking->update(['booking_status' => Booking::BOOKING_STATUS_COMPLETED]);
        self::logManualLifecycleTrigger($booking, 'checkout_triggered');
        $actor = auth()->user();
        BookingDamageSettlement::syncFromChecklist(
            $booking->fresh(['roomChecklists.items']),
            $actor instanceof User ? $actor : null,
        );
    }

    /**
     * @return list<array{id: int, room_name: string, label: string, charge: string, status: string, notes: string}>
     */
    public static function checkoutChecklistFormItems(Booking $booking): array
    {
        self::ensureCompletionRoomChecklists($booking);
        $booking->unsetRelation('roomChecklists');
        $booking->load(['roomChecklists.room', 'roomChecklists.items']);

        return $booking->roomChecklists
            ->flatMap(function (RoomChecklist $checklist) {
                $roomName = (string) ($checklist->room?->name ?? 'Room');

                return $checklist->items->map(function (RoomChecklistItem $item) use ($roomName): array {
                    return [
                        'id' => (int) $item->id,
                        'room_name' => $roomName,
                        'label' => (string) $item->label,
                        'charge' => (string) $item->charge,
                        'status' => (string) ($item->status ?: RoomChecklistItem::STATUS_GOOD),
                        'notes' => (string) ($item->notes ?? ''),
                    ];
                });
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{id?: mixed, status?: mixed, notes?: mixed}>  $rows
     */
    public static function saveCheckoutChecklistItems(Booking $booking, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        self::ensureCompletionRoomChecklists($booking);
        $booking->unsetRelation('roomChecklists');
        $booking->load(['roomChecklists.items']);

        $validStatuses = [
            RoomChecklistItem::STATUS_GOOD,
            RoomChecklistItem::STATUS_BROKEN,
            RoomChecklistItem::STATUS_MISSING,
        ];

        DB::transaction(function () use ($booking, $rows, $validStatuses): void {
            $allowedIds = $booking->roomChecklists
                ->flatMap(fn (RoomChecklist $checklist) => $checklist->items->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            foreach ($rows as $row) {
                $itemId = (int) ($row['id'] ?? 0);
                if ($itemId <= 0 || ! $allowedIds->contains($itemId)) {
                    continue;
                }

                $status = (string) ($row['status'] ?? RoomChecklistItem::STATUS_GOOD);
                if (! in_array($status, $validStatuses, true)) {
                    $status = RoomChecklistItem::STATUS_GOOD;
                }

                RoomChecklistItem::query()
                    ->whereKey($itemId)
                    ->update([
                        'status' => $status,
                        'notes' => filled($row['notes'] ?? null) ? trim((string) $row['notes']) : null,
                    ]);
            }
        });
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function cancel(Booking $booking): void
    {
        if ($booking->trashed()) {
            throw new \InvalidArgumentException(__('Cannot cancel a deleted booking.'));
        }

        if (in_array($booking->booking_status, [Booking::BOOKING_STATUS_CANCELLED, Booking::BOOKING_STATUS_COMPLETED], true)) {
            throw new \InvalidArgumentException(__('This booking is already cancelled or completed.'));
        }

        $booking->update(['booking_status' => Booking::BOOKING_STATUS_CANCELLED]);
    }

    private static function logManualLifecycleTrigger(Booking $booking, string $event): void
    {
        $actor = auth()->user();
        if (! $actor instanceof User || ! in_array((string) $actor->role, ['admin', 'staff'], true)) {
            return;
        }

        ActivityLogger::log(
            category: 'booking',
            event: $event,
            description: sprintf(
                '%s manually triggered %s for booking %s.',
                $actor->name,
                str_replace('_', ' ', $event),
                $booking->reference_number,
            ),
            subject: $booking,
            meta: [
                'reference_number' => $booking->reference_number,
                'triggered_by_user_id' => (int) $actor->id,
                'triggered_by_user_name' => (string) $actor->name,
                'trigger' => $event,
                'booking_status' => (string) $booking->booking_status,
                'payment_status' => (string) $booking->payment_status,
            ],
            userId: (int) $actor->id,
        );
    }

    private static function ensureCompletionRoomChecklists(Booking $booking): void
    {
        $booking->loadMissing(['rooms.damageProperties']);

        if ($booking->rooms->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($booking): void {
            foreach ($booking->rooms as $room) {
                $checklist = RoomChecklist::query()->firstOrCreate(
                    [
                        'booking_id' => (int) $booking->id,
                        'room_id' => (int) $room->id,
                    ],
                    [
                        'generated_at' => now(),
                    ],
                );

                if ($checklist->items()->exists()) {
                    continue;
                }

                $items = self::buildChecklistItemsFromRoomDamageProperties($room);

                if ($items !== []) {
                    $checklist->items()->createMany($items);
                }
            }
        });
    }

    /**
     * @return list<array{label: string, charge: string, status: string, notes: null, sort_order: int}>
     */
    private static function buildChecklistItemsFromRoomDamageProperties(Room $room): array
    {
        $room->loadMissing(['damageProperties']);

        return $room->damageProperties
            ->sortBy('name')
            ->values()
            ->map(function ($damageProperty, int $index): array {
                return [
                    'label' => (string) $damageProperty->name,
                    'charge' => trim((string) ($damageProperty->default_charge ?? '')),
                    'status' => RoomChecklistItem::STATUS_GOOD,
                    'notes' => null,
                    'sort_order' => $index,
                ];
            })
            ->all();
    }
}
