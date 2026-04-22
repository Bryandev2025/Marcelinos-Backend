<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BookingDuplicateGuard
{
    /**
     * Block creating another active booking for the same guest email with overlapping stay window.
     *
     * @throws ValidationException
     */
    public function assertNoOverlappingActiveBooking(string $email, Carbon $checkIn, Carbon $checkOut): void
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return;
        }

        $exists = Booking::query()
            ->whereHas('guest', function ($q) use ($normalized): void {
                $q->whereRaw('LOWER(TRIM(email)) = ?', [$normalized]);
            })
            ->where('booking_status', '!=', Booking::BOOKING_STATUS_CANCELLED)
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => ['You already have an active booking that overlaps these dates.'],
            ]);
        }
    }
}
