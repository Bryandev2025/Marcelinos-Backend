<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Collection;

final class BookingDoubleBookDetector
{
    /**
     * Other non-cancelled bookings whose stay overlaps this booking's range and share at least one
     * assigned room or venue. Rooms use the classic interval overlap; venue shares use
     * {@see VenueWeddingPreparation} (wedding + venue prep day).
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

        $effThis = VenueWeddingPreparation::effectiveVenueBlockStart(
            $checkIn,
            $booking->venue_event_type,
            $venueIds !== [],
        );

        $query = Booking::query()
            ->whereKeyNot($booking->getKey())
            ->whereIn('booking_status', Booking::availabilityBlockingStatuses())
            ->where(function ($outer) use ($roomIds, $venueIds, $checkIn, $checkOut, $effThis) {
                if ($roomIds !== [] && $venueIds !== []) {
                    $outer->where(function ($q) use ($roomIds, $checkIn, $checkOut): void {
                        $q->whereHas('rooms', fn ($rq) => $rq->whereIn('rooms.id', $roomIds))
                            ->where('check_in', '<', $checkOut)
                            ->where('check_out', '>', $checkIn);
                    })->orWhere(function ($q) use ($venueIds, $effThis, $checkOut): void {
                        $q->whereHas('venues', fn ($vq) => $vq->whereIn('venues.id', $venueIds));
                        VenueWeddingPreparation::constrainToBookingsThatCollideWithVenueCandidateRange(
                            $q,
                            $effThis,
                            $checkOut,
                            null
                        );
                    });

                    return;
                }
                if ($roomIds !== []) {
                    $outer->where(function ($q) use ($roomIds, $checkIn, $checkOut): void {
                        $q->whereHas('rooms', fn ($rq) => $rq->whereIn('rooms.id', $roomIds))
                            ->where('check_in', '<', $checkOut)
                            ->where('check_out', '>', $checkIn);
                    });

                    return;
                }
                $outer->where(function ($q) use ($venueIds, $effThis, $checkOut): void {
                    $q->whereHas('venues', fn ($vq) => $vq->whereIn('venues.id', $venueIds));
                    VenueWeddingPreparation::constrainToBookingsThatCollideWithVenueCandidateRange(
                        $q,
                        $effThis,
                        $checkOut,
                        null
                    );
                });
            });

        /** @var Collection<int, Booking> */
        return $query->orderBy('check_in')->get();
    }
}
