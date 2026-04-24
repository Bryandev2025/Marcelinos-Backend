<?php

namespace Tests\Unit;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingUnpaidExpiryTest extends TestCase
{
    private static function manila(string $datetime): Carbon
    {
        return Carbon::parse($datetime, Booking::timezoneManila());
    }

    #[Test]
    public function future_check_in_is_not_expired_before_tomorrow_21_00(): void
    {
        $booking = new Booking([
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-04 10:00:00');
        $booking->check_in = self::manila('2026-04-20 14:00:00');

        $at = self::manila('2026-04-05 13:00:00');

        $this->assertTrue($booking->isCheckInStrictlyAfterTodayManila($at));
        $this->assertFalse($booking->isExpiredUnpaid($at));
    }

    #[Test]
    public function future_check_in_expires_at_or_after_tomorrow_21_00_manila(): void
    {
        $booking = new Booking([
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-05 10:00:00');
        $booking->check_in = self::manila('2026-04-20 14:00:00');

        $beforeDeadline = self::manila('2026-04-06 20:59:00');
        $this->assertFalse($booking->isExpiredUnpaid($beforeDeadline));

        $atDeadline = self::manila('2026-04-06 21:00:00');
        $this->assertTrue($booking->isExpiredUnpaid($atDeadline));
    }

    /**
     * Short-lead booking: cancel-unpaid evaluates all unpaid rows (no created_at cutoff in model).
     */
    #[Test]
    public function short_lead_booking_expires_at_21_00_on_check_in_day(): void
    {
        $booking = new Booking([
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-14 10:00:00');
        $booking->check_in = self::manila('2026-04-15 14:00:00');

        $this->assertFalse($booking->isExpiredUnpaid(self::manila('2026-04-15 20:59:00')));
        $this->assertTrue($booking->isExpiredUnpaid(self::manila('2026-04-15 21:00:00')));
    }

    #[Test]
    public function messenger_path_unpaid_expires_at_is_tomorrow_21_00_for_future_check_in(): void
    {
        $booking = new Booking([
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-10 10:00:00');
        $booking->check_in = self::manila('2026-04-16 14:00:00');

        Carbon::setTestNow(self::manila('2026-04-10 12:00:00'));

        try {
            $this->assertTrue($booking->useMessengerDepositInstructions());
            $expires = $booking->unpaidExpiresAt();
            $this->assertNotNull($expires);
            $this->assertSame('2026-04-11', $expires->timezone(Booking::timezoneManila())->format('Y-m-d'));
            $this->assertSame('21:00:00', $expires->timezone(Booking::timezoneManila())->format('H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function same_calendar_day_booking_not_expired_before_21_00_on_check_in_day(): void
    {
        $booking = new Booking([
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-10 09:00:00');
        $booking->check_in = self::manila('2026-04-10 15:00:00');

        $this->assertFalse($booking->isExpiredUnpaid(self::manila('2026-04-10 20:59:00')));
        $this->assertTrue($booking->isExpiredUnpaid(self::manila('2026-04-10 21:00:00')));
    }

    #[Test]
    public function past_check_in_day_is_expired_when_unpaid_after_deadline(): void
    {
        $booking = new Booking([
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-01 10:00:00');
        $booking->check_in = self::manila('2026-04-05 14:00:00');

        $at = self::manila('2026-04-06 10:00:00');
        $this->assertTrue($booking->isExpiredUnpaid($at));
    }

    #[Test]
    public function unpaid_expires_at_matches_check_in_day_21_00_when_not_messenger_path(): void
    {
        $booking = new Booking([
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-10 09:00:00');
        $booking->check_in = self::manila('2026-04-10 15:00:00');

        Carbon::setTestNow(self::manila('2026-04-10 11:00:00'));

        try {
            $this->assertFalse($booking->useMessengerDepositInstructions());
            $expires = $booking->unpaidExpiresAt();
            $this->assertNotNull($expires);
            $this->assertSame('2026-04-10', $expires->timezone(Booking::timezoneManila())->format('Y-m-d'));
            $this->assertSame('21:00:00', $expires->timezone(Booking::timezoneManila())->format('H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function day_before_check_in_is_not_expired_even_late_at_night(): void
    {
        $booking = new Booking([
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-10 10:00:00');
        $booking->check_in = self::manila('2026-04-11 12:00:00');

        $this->assertFalse($booking->isExpiredUnpaid(self::manila('2026-04-10 23:59:00')));
    }

    #[Test]
    public function far_future_check_in_still_expires_tomorrow_at_21_00(): void
    {
        $booking = new Booking([
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
        ]);
        $booking->created_at = self::manila('2026-04-05 10:00:00');
        $booking->check_in = self::manila('2026-04-30 14:00:00');

        $this->assertFalse($booking->isExpiredUnpaid(self::manila('2026-04-06 20:59:00')));
        $this->assertTrue($booking->isExpiredUnpaid(self::manila('2026-04-06 21:00:00')));
    }
}
