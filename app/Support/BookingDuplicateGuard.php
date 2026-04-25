<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BookingDuplicateGuard
{
    /**
     * Block when the same guest email already has a non-cancelled booking with identical
     * room lines, venues, date window, days, and payment options (double-submit guard).
     *
     * @param  array<int, array<string, mixed>>  $roomLines
     * @param  array<int, int>  $venueIds
     *
     * @throws ValidationException
     */
    public function assertNotIdenticalActiveBooking(
        string $email,
        Carbon $checkIn,
        Carbon $checkOut,
        int $days,
        array $roomLines,
        array $venueIds,
        ?string $venueEventType,
        string $paymentMethod,
        string $onlinePaymentPlan,
    ): void {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return;
        }

        $days = max(1, $days);
        $venueEventType = $this->compareVenueEventType($venueIds, $venueEventType);
        $requestRoomFingerprint = $this->normalizeRequestRoomLines($roomLines);
        $requestVenueIds = $this->sortVenueIds($venueIds);
        $paymentMethod = (string) $paymentMethod;
        $onlinePaymentPlan = (string) $onlinePaymentPlan;

        $candidates = Booking::query()
            ->whereHas('guest', function ($q) use ($normalized): void {
                $q->whereRaw('LOWER(TRIM(email)) = ?', [$normalized]);
            })
            ->where('booking_status', '!=', Booking::BOOKING_STATUS_CANCELLED)
            ->where('no_of_days', $days)
            ->whereDate('check_in', $checkIn->toDateString())
            ->whereDate('check_out', $checkOut->toDateString())
            ->with(['roomLines', 'venues'])
            ->get();

        foreach ($candidates as $booking) {
            if (! $this->datetimesMatchStorageWindow($checkIn, $checkOut, $booking)) {
                continue;
            }
            if ((string) ($booking->payment_method ?? 'cash') !== $paymentMethod) {
                continue;
            }
            if ((string) ($booking->online_payment_plan ?? '') !== $onlinePaymentPlan) {
                continue;
            }
            if ($this->compareVenueEventTypeFromBooking($booking) !== $venueEventType) {
                continue;
            }
            if (! $this->roomLineFingerprintsMatch($requestRoomFingerprint, $booking->roomLines)) {
                continue;
            }
            if (! $this->venueIdsMatch($requestVenueIds, $booking)) {
                continue;
            }

            throw ValidationException::withMessages([
                'email' => ['A booking with the same room and venue details for these dates is already on file.'],
            ]);
        }
    }

    /**
     * @param  array<int, int>  $venueIds
     */
    private function compareVenueEventType(array $venueIds, ?string $venueEventType): ?string
    {
        if (count($venueIds) === 0) {
            return null;
        }

        return BookingPricing::normalizeVenueEventType($venueEventType);
    }

    private function compareVenueEventTypeFromBooking(Booking $booking): ?string
    {
        if ($booking->venues->isEmpty()) {
            return null;
        }

        return BookingPricing::normalizeVenueEventType($booking->venue_event_type);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: int, 3: float}>  $a
     */
    private function roomLineFingerprintsMatch(array $a, Collection $roomLines): bool
    {
        $b = $this->normalizeBookingRoomLines($roomLines);
        if (count($a) !== count($b)) {
            return false;
        }
        for ($i = 0, $c = count($a); $i < $c; $i++) {
            if ($a[$i][0] !== $b[$i][0] || $a[$i][1] !== $b[$i][1] || $a[$i][2] !== $b[$i][2]) {
                return false;
            }
            if (! BookingPricing::totalsMatch($a[$i][3], $b[$i][3])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: int, 3: float}
     */
    private function normalizeRequestRoomLines(array $roomLines): array
    {
        $out = [];
        foreach ($roomLines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $out[] = [
                (string) ($line['room_type'] ?? ''),
                (string) ($line['inventory_group_key'] ?? ''),
                (int) ($line['quantity'] ?? 0),
                (float) ($line['unit_price'] ?? 0),
            ];
        }
        usort($out, $this->roomLineSort(...));

        return $out;
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: int, 3: float}
     */
    private function normalizeBookingRoomLines(Collection $roomLines): array
    {
        $out = [];
        foreach ($roomLines as $line) {
            $out[] = [
                (string) $line->room_type,
                (string) $line->inventory_group_key,
                (int) $line->quantity,
                (float) $line->unit_price_per_night,
            ];
        }
        usort($out, $this->roomLineSort(...));

        return $out;
    }

    /**
     * @param  array{0: string, 1: string, 2: int, 3: float}  $a
     * @param  array{0: string, 1: string, 2: int, 3: float}  $b
     */
    private function roomLineSort(array $a, array $b): int
    {
        return $a[0] <=> $b[0]
            ?: $a[1] <=> $b[1]
            ?: $a[2] <=> $b[2]
            ?: $a[3] <=> $b[3];
    }

    /**
     * @param  array<int, int>  $sortedVenueIds
     */
    private function venueIdsMatch(array $sortedVenueIds, Booking $booking): bool
    {
        $ids = $booking->venues->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        return $ids === $sortedVenueIds;
    }

    /**
     * @param  array<int, int>  $venueIds
     * @return array<int, int>
     */
    private function sortVenueIds(array $venueIds): array
    {
        $ids = array_map(static fn ($id): int => (int) $id, array_values($venueIds));
        sort($ids);

        return $ids;
    }

    /**
     * Match stored windows after DB round-trip (microsecond truncation, etc.).
     */
    private function datetimesMatchStorageWindow(Carbon $checkIn, Carbon $checkOut, Booking $booking): bool
    {
        $bi = $booking->check_in;
        $bo = $booking->check_out;
        if ($bi === null || $bo === null) {
            return false;
        }

        return abs($checkIn->getTimestamp() - $bi->getTimestamp()) <= 2
            && abs($checkOut->getTimestamp() - $bo->getTimestamp()) <= 2;
    }
}
