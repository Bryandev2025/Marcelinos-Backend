<?php

namespace App\Support;

use App\Models\Booking;

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

        $booking->update(['status' => Booking::STATUS_OCCUPIED]);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function complete(Booking $booking): void
    {
        if ($booking->trashed()) {
            throw new \InvalidArgumentException(__('Cannot complete a deleted booking.'));
        }

        if ($booking->status !== Booking::STATUS_OCCUPIED) {
            throw new \InvalidArgumentException(__('Booking must be checked in (occupied) before it can be completed.'));
        }

        $booking->update(['status' => Booking::STATUS_COMPLETED]);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function cancel(Booking $booking): void
    {
        if ($booking->trashed()) {
            throw new \InvalidArgumentException(__('Cannot cancel a deleted booking.'));
        }

        if (in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED], true)) {
            throw new \InvalidArgumentException(__('This booking is already cancelled or completed.'));
        }

        $booking->update(['status' => Booking::STATUS_CANCELLED]);
    }
}
