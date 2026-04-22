<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Venue;
use App\Services\BookingActionOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    public function reschedule_to_shorter_stay_marks_overpaid_booking_as_refunded(): void
    {
        Mail::fake();
        $this->mockRescheduleOtpVerification();

        [$booking, $venue] = $this->createFullyPaidVenueBooking(days: 2, venueDayPrice: 1000);

        $response = $this->patchJson("/api/bookings/{$booking->reference_number}/reschedule", [
            'check_in' => '2026-05-10',
            'check_out' => '2026-05-11', // 1 day
            'otp' => '123456',
        ]);

        $response->assertOk()->assertJsonPath('message', 'Booking rescheduled successfully');

        $booking->refresh();
        $booking->load('venues');

        $this->assertSame(Booking::PAYMENT_STATUS_REFUNDED, (string) $booking->payment_status);
        $this->assertSame(1000.0, (float) $booking->total_price);
        $this->assertSame(2000.0, (float) $booking->total_paid);
        $this->assertTrue($booking->venues->contains('id', $venue->id));
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
