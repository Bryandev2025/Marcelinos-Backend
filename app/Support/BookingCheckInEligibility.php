<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for whether staff can check a booking in (→ status occupied).
 */
final class BookingCheckInEligibility
{
    public const REASON_OK = 'ok';

    public const REASON_TRASHED = 'trashed';

    public const REASON_INVALID_STATUS = 'invalid_status';

    public const REASON_OUTSIDE_CHECK_IN_DAY = 'outside_check_in_day';

    public const REASON_ASSIGNMENTS = 'assignments';

    /**
     * @return array{allowed: bool, reason: string, message: ?string}
     */
    public static function assess(Booking $booking): array
    {
        if ($booking->trashed()) {
            return ['allowed' => false, 'reason' => self::REASON_TRASHED, 'message' => null];
        }

        if (! $booking->isCheckInTodayManila()) {
            return [
                'allowed' => false,
                'reason' => self::REASON_OUTSIDE_CHECK_IN_DAY,
                'message' => __('Booking can only be checked in on the check-in date.'),
            ];
        }

        if ($booking->payment_status !== Booking::PAYMENT_STATUS_PAID) {
            return [
                'allowed' => false,
                'reason' => self::REASON_INVALID_STATUS,
                'message' => __('Booking must be fully paid before check-in.'),
            ];
        }

        $booking->loadMissing(['roomLines', 'venues', 'rooms.bedSpecifications']);

        try {
            $booking->assertAssignmentsSatisfiedForOccupied();
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();

            return ['allowed' => false, 'reason' => self::REASON_ASSIGNMENTS, 'message' => $message];
        }

        return ['allowed' => true, 'reason' => self::REASON_OK, 'message' => null];
    }
}
