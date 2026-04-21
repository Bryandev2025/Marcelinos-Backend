<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Collection;

final class BookingDoubleBookDetector
{
    /**
     * Other non-cancelled bookings whose stay overlaps this booking's range and share at least one
     * assigned room or venue (same rules as availability overlap: check_in < other check_out and check_out > other check_in).
     *
     * @return Collection<int, Booking>
     */
    public static function overlappingBookings(Booking $booking): Collection
    {
        if ($booking->check_in === null || $booking->check_out === null) {
            return new Collection;
        }

        if ($booking->booking_status === Booking::BOOKING_STATUS_CANCELLED) {
            return new Collection;
        }

        $roomIds = $booking->rooms()->pluck('rooms.id')->all();
        $venueIds = $booking->venues()->pluck('venues.id')->all();

        if ($roomIds === [] && $venueIds === []) {
            return new Collection;
        }

        $checkIn = $booking->check_in;
        $checkOut = $booking->check_out;

        $query = Booking::query()
            ->whereKeyNot($booking->getKey())
            ->where('booking_status', '!=', Booking::BOOKING_STATUS_CANCELLED)
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn);

        $query->where(function ($q) use ($roomIds, $venueIds): void {
            if ($roomIds !== []) {
                $q->whereHas('rooms', fn ($rq) => $rq->whereIn('rooms.id', $roomIds));
            }
            if ($venueIds !== []) {
                if ($roomIds !== []) {
                    $q->orWhereHas('venues', fn ($vq) => $vq->whereIn('venues.id', $venueIds));
                } else {
                    $q->whereHas('venues', fn ($vq) => $vq->whereIn('venues.id', $venueIds));
                }
            }
        });

        /** @var Collection<int, Booking> */
        return $query->orderBy('check_in')->get();
    }
}
