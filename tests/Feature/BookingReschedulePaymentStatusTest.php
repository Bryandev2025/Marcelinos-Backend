<?php

namespace Tests\Feature;

use App\Mail\RefundActionRequiredStaffMail;
use App\Mail\RefundCompletedGuestMail;
use App\Mail\RefundEligibleGuestMail;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Venue;
use App\Services\BookingActionOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingReschedulePaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reschedule_to_longer_stay_marks_paid_booking_as_partial(): void
    {
        Mail::fake();
        $this->mockRescheduleOtpVerification();

        [$booking, $venue] = $this->createFullyPaidVenueBooking(days: 2, venueDayPrice: 1000);

        $response = $this->patchJson("/api/bookings/{$booking->reference_number}/reschedule", [
            'check_in' => '2026-05-10',
            'check_out' => '2026-05-13', // 3 days
            'otp' => '123456',
        ]);

        $response->assertOk()->assertJsonPath('message', 'Booking rescheduled successfully');

        $booking->refresh();
        $booking->load('venues');

        $this->assertSame(Booking::PAYMENT_STATUS_PARTIAL, (string) $booking->payment_status);
        $this->assertSame(3000.0, (float) $booking->total_price);
        $this->assertSame(2000.0, (float) $booking->total_paid);
        $this->assertTrue($booking->venues->contains('id', $venue->id));
    }

    #[Test]
    public function reschedule_to_shorter_stay_marks_overpaid_booking_as_refund_pending(): void
    {
        Mail::fake();
        $this->mockRescheduleOtpVerification();
        config()->set('notifications.refund_guest_eligible_enabled', false);
        config()->set('notifications.refund_guest_completed_enabled', true);
        config()->set('notifications.refund_staff_alert_enabled', true);
        config()->set('notifications.refund_staff_recipients', ['ops@example.test']);

        [$booking, $venue] = $this->createFullyPaidVenueBooking(days: 2, venueDayPrice: 1000);

        $response = $this->patchJson("/api/bookings/{$booking->reference_number}/reschedule", [
            'check_in' => '2026-05-10',
            'check_out' => '2026-05-11', // 1 day
            'otp' => '123456',
        ]);

        $response->assertOk()->assertJsonPath('message', 'Booking rescheduled successfully');

        $booking->refresh();
        $booking->load('venues');

        $this->assertSame(Booking::PAYMENT_STATUS_REFUND_PENDING, (string) $booking->payment_status);
        $this->assertSame(1000.0, (float) $booking->total_price);
        $this->assertSame(2000.0, (float) $booking->total_paid);
        $this->assertTrue($booking->venues->contains('id', $venue->id));
        $this->assertNull($booking->refund_guest_confirmation_sent_at);
        $this->assertNotNull($booking->refund_alert_sent_at);

        Mail::assertNotSent(RefundCompletedGuestMail::class);
        Mail::assertSent(RefundActionRequiredStaffMail::class, 1);
    }

    #[Test]
    public function refund_pending_state_is_idempotent_and_does_not_send_duplicate_emails_on_repeated_save(): void
    {
        Mail::fake();
        $this->mockRescheduleOtpVerification();
        config()->set('notifications.refund_guest_eligible_enabled', false);
        config()->set('notifications.refund_guest_completed_enabled', true);
        config()->set('notifications.refund_staff_alert_enabled', true);
        config()->set('notifications.refund_staff_recipients', ['ops@example.test']);

        [$booking] = $this->createFullyPaidVenueBooking(days: 2, venueDayPrice: 1000);

        $this->patchJson("/api/bookings/{$booking->reference_number}/reschedule", [
            'check_in' => '2026-05-10',
            'check_out' => '2026-05-11',
            'otp' => '123456',
        ])->assertOk();

        $booking->refresh();
        $this->assertSame(Booking::PAYMENT_STATUS_REFUND_PENDING, (string) $booking->payment_status);

        // Save again without changing payment_status. Observer should not resend.
        $booking->update(['total_price' => $booking->total_price]);

        Mail::assertNotSent(RefundCompletedGuestMail::class);
        Mail::assertSent(RefundActionRequiredStaffMail::class, 1);
    }

    #[Test]
    public function refund_pending_notifications_respect_feature_flags(): void
    {
        Mail::fake();
        $this->mockRescheduleOtpVerification();
        config()->set('notifications.refund_guest_eligible_enabled', false);
        config()->set('notifications.refund_guest_completed_enabled', false);
        config()->set('notifications.refund_staff_alert_enabled', false);
        config()->set('notifications.refund_staff_recipients', ['ops@example.test']);

        [$booking] = $this->createFullyPaidVenueBooking(days: 2, venueDayPrice: 1000);

        $this->patchJson("/api/bookings/{$booking->reference_number}/reschedule", [
            'check_in' => '2026-05-10',
            'check_out' => '2026-05-11',
            'otp' => '123456',
        ])->assertOk();

        $booking->refresh();
        $this->assertNull($booking->refund_guest_confirmation_sent_at);
        $this->assertNull($booking->refund_alert_sent_at);
        Mail::assertNotSent(RefundCompletedGuestMail::class);
        Mail::assertNotSent(RefundActionRequiredStaffMail::class);
    }

    #[Test]
    public function mail_failure_does_not_block_reschedule_flow(): void
    {
        Mail::fake();
        $this->mockRescheduleOtpVerification();
        config()->set('notifications.refund_guest_eligible_enabled', false);
        config()->set('notifications.refund_guest_completed_enabled', true);
        config()->set('notifications.refund_staff_alert_enabled', true);
        config()->set('notifications.refund_staff_recipients', ['ops@example.test']);

        [$booking] = $this->createFullyPaidVenueBooking(days: 2, venueDayPrice: 1000);

        Mail::shouldReceive('to')
            ->andReturn(new class
            {
                public function send(mixed $mailable): void
                {
                    throw new \RuntimeException('smtp unavailable');
                }
            });

        Log::spy();

        $response = $this->patchJson("/api/bookings/{$booking->reference_number}/reschedule", [
            'check_in' => '2026-05-10',
            'check_out' => '2026-05-11',
            'otp' => '123456',
        ]);

        $response->assertOk()->assertJsonPath('message', 'Booking rescheduled successfully');
        $booking->refresh();
        $this->assertSame(Booking::PAYMENT_STATUS_REFUND_PENDING, (string) $booking->payment_status);
        $this->assertNull($booking->refund_guest_confirmation_sent_at);
        $this->assertNull($booking->refund_alert_sent_at);

        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    #[Test]
    public function marking_refund_completed_sends_guest_completed_notice_once(): void
    {
        Mail::fake();
        config()->set('notifications.refund_guest_completed_enabled', true);
        config()->set('notifications.refund_staff_alert_enabled', false);

        [$booking] = $this->createFullyPaidVenueBooking(days: 1, venueDayPrice: 1000);
        $booking->update([
            'booking_status' => Booking::BOOKING_STATUS_RESCHEDULED,
            'total_price' => 500.0,
            'payment_status' => Booking::PAYMENT_STATUS_REFUND_PENDING,
            'refund_guest_confirmation_sent_at' => null,
        ]);

        $booking->update([
            'payment_status' => Booking::PAYMENT_STATUS_REFUNDED,
        ]);

        $booking->refresh();
        $this->assertNotNull($booking->refund_guest_confirmation_sent_at);
        Mail::assertSent(RefundCompletedGuestMail::class, 1);
        Mail::assertNotSent(RefundEligibleGuestMail::class);
    }

    private function mockRescheduleOtpVerification(): void
    {
        $otpService = \Mockery::mock(BookingActionOtpService::class);
        $otpService->shouldReceive('verifyAndConsume')
            ->once()
            ->andReturn(true);

        $this->instance(BookingActionOtpService::class, $otpService);
    }

    /**
     * @return array{Booking, Venue}
     */
    private function createFullyPaidVenueBooking(int $days, int $venueDayPrice): array
    {
        $guest = Guest::query()->create([
            'first_name' => 'Resched',
            'middle_name' => null,
            'last_name' => 'Guest',
            'contact_num' => '09171234567',
            'email' => 'reschedule-status@example.test',
            'gender' => Guest::GENDER_OTHER,
        ]);

        $venue = Venue::query()->create([
            'name' => 'Reschedule Venue',
            'description' => 'Feature test venue',
            'capacity' => 100,
            'price' => $venueDayPrice,
            'wedding_price' => $venueDayPrice,
            'birthday_price' => $venueDayPrice,
            'meeting_staff_price' => $venueDayPrice,
            'status' => Venue::STATUS_AVAILABLE,
        ]);

        $booking = Booking::query()->create([
            'guest_id' => $guest->id,
            'check_in' => '2026-05-10 00:00:00',
            'check_out' => '2026-05-12 00:00:00',
            'no_of_days' => $days,
            'total_price' => $venueDayPrice * $days,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_PAID,
            'venue_event_type' => 'wedding',
        ]);

        $booking->venues()->attach($venue->id);

        Payment::query()->create([
            'booking_id' => $booking->id,
            'total_amount' => $venueDayPrice * $days,
            'partial_amount' => $venueDayPrice * $days,
            'is_fullypaid' => true,
            'provider' => 'cash',
            'provider_ref' => 'test-reschedule-paid-'.$booking->id,
            'provider_status' => 'paid',
        ]);

        return [$booking->fresh(), $venue];
    }
}
