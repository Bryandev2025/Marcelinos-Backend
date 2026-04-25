<?php

namespace Tests\Feature;

use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Mail\RefundActionRequiredStaffMail;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Venue;
use App\Services\BookingActionOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class BookingGuestCancelRefundTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_cancel_of_paid_booking_sets_refund_pending_and_returns_cancellation_breakdown(): void
    {
        Mail::fake();
        $this->mockCancelOtpVerification();
        config()->set('notifications.refund_guest_eligible_enabled', false);
        config()->set('notifications.refund_staff_alert_enabled', true);
        config()->set('notifications.refund_staff_recipients', ['ops@example.test']);

        [$booking] = $this->createFullyPaidVenueBooking(days: 2, venueDayPrice: 1000);

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->reference_number}/cancel", [
            'otp' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Booking cancelled successfully.')
            ->assertJsonPath('cancellation.fee_percent', 30);

        $amountPaid = $response->json('cancellation.amount_paid');
        $this->assertEqualsWithDelta(2000.0, (float) $amountPaid, 0.001);

        $booking->refresh();
        $this->assertSame(Booking::BOOKING_STATUS_CANCELLED, (string) $booking->booking_status);
        $this->assertSame(Booking::PAYMENT_STATUS_REFUND_PENDING, (string) $booking->payment_status);

        Mail::assertSent(RefundActionRequiredStaffMail::class, 1);

        $token = (string) $booking->receipt_token;
        $this->assertNotSame('', $token);

        $receipt = $this->withHeaders($this->apiHeaders())->getJson("/api/bookings/receipt/{$token}");
        $receipt->assertOk();
        $receipt->assertJsonPath('cancellation_refund.fee_percent', 30);
        $cr = $receipt->json('cancellation_refund');
        $this->assertIsArray($cr);
        $this->assertEqualsWithDelta(600.0, (float) ($cr['retained'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(1400.0, (float) ($cr['refund_to_guest'] ?? 0), 0.01);
    }

    #[Test]
    public function guest_cancel_of_partially_paid_booking_retains_deposit_with_zero_refund(): void
    {
        Mail::fake();
        $this->mockCancelOtpVerification();
        config()->set('notifications.refund_guest_eligible_enabled', false);
        config()->set('notifications.refund_staff_alert_enabled', true);
        config()->set('notifications.refund_staff_recipients', ['ops@example.test']);

        $booking = $this->createPartiallyPaidReservedVenueBooking(
            days: 2,
            venueDayPrice: 1000,
            paidAmount: 600.0,
        );

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->reference_number}/cancel", [
            'otp' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Booking cancelled successfully.');
        $this->assertSame('partial_deposit', $response->json('cancellation.settlement_type'));
        $this->assertFalse($response->json('cancellation.applies_cancellation_percent'));
        $this->assertEqualsWithDelta(0.0, (float) $response->json('cancellation.amount_to_refund'), 0.001);
        $this->assertEqualsWithDelta(600.0, (float) $response->json('cancellation.amount_to_keep'), 0.001);

        $token = (string) $booking->fresh()->receipt_token;
        $receipt = $this->withHeaders($this->apiHeaders())->getJson("/api/bookings/receipt/{$token}");
        $receipt->assertOk();
        $this->assertFalse($receipt->json('cancellation_refund.applies_cancellation_percent'));
        $this->assertEqualsWithDelta(0.0, (float) $receipt->json('cancellation_refund.refund_to_guest'), 0.01);
        $this->assertStringContainsString('reservation', (string) $receipt->json('cancellation_refund.statement_note'));
    }

    #[Test]
    public function guest_cancel_of_unpaid_booking_leaves_payment_unpaid(): void
    {
        Mail::fake();
        $this->mockCancelOtpVerification();

        $booking = $this->createUnpaidReservedVenueBooking();

        $response = $this->withHeaders($this->apiHeaders())->patchJson("/api/bookings/{$booking->reference_number}/cancel", [
            'otp' => '123456',
        ]);

        $response->assertOk();

        $booking->refresh();
        $this->assertSame(Booking::BOOKING_STATUS_CANCELLED, (string) $booking->booking_status);
        $this->assertSame(Booking::PAYMENT_STATUS_UNPAID, (string) $booking->payment_status);

        Mail::assertNotSent(RefundActionRequiredStaffMail::class);
    }

    #[Test]
    public function edit_booking_does_not_overwrite_refund_pending_with_paid_when_amounts_match_total(): void
    {
        [$booking] = $this->createFullyPaidVenueBooking(days: 2, venueDayPrice: 1000);
        $booking->update([
            'booking_status' => Booking::BOOKING_STATUS_CANCELLED,
            'payment_status' => Booking::PAYMENT_STATUS_REFUND_PENDING,
        ]);
        $booking->refresh();

        /** @var EditBooking $page */
        $page = app(EditBooking::class);
        $page->record = $booking;

        $mutate = new ReflectionMethod(EditBooking::class, 'mutateFormDataBeforeSave');
        $mutate->setAccessible(true);

        $out = $mutate->invoke($page, [
            'booking_status' => Booking::BOOKING_STATUS_CANCELLED,
            'payment_status' => Booking::PAYMENT_STATUS_REFUND_PENDING,
            'total_price' => (string) $booking->total_price,
            'venues' => [],
            'rooms' => [],
        ]);

        $this->assertSame(Booking::PAYMENT_STATUS_REFUND_PENDING, $out['payment_status']);
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        config()->set('services.api.key', 'test-api-key');

        return [
            'x-api-key' => 'test-api-key',
            'Accept' => 'application/json',
        ];
    }

    private function mockCancelOtpVerification(): void
    {
        $otpService = \Mockery::mock(BookingActionOtpService::class);
        $otpService->shouldReceive('verifyAndConsume')
            ->andReturn(true);

        $this->instance(BookingActionOtpService::class, $otpService);
    }

    /**
     * @return array{Booking, Venue}
     */
    private function createFullyPaidVenueBooking(int $days, int $venueDayPrice): array
    {
        $guest = Guest::query()->create([
            'first_name' => 'Cancel',
            'middle_name' => null,
            'last_name' => 'Guest',
            'contact_num' => '09171234567',
            'email' => 'cancel-refund@example.test',
            'gender' => Guest::GENDER_OTHER,
        ]);

        $venue = Venue::query()->create([
            'name' => 'Cancel Test Venue',
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
            'provider_ref' => 'test-cancel-paid-'.$booking->id,
            'provider_status' => 'paid',
        ]);

        return [$booking->fresh(), $venue];
    }

    private function createPartiallyPaidReservedVenueBooking(int $days, int $venueDayPrice, float $paidAmount): Booking
    {
        $guest = Guest::query()->create([
            'first_name' => 'Partial',
            'middle_name' => null,
            'last_name' => 'Cancel',
            'contact_num' => '09171234570',
            'email' => 'partial-cancel@example.test',
            'gender' => Guest::GENDER_OTHER,
        ]);

        $venue = Venue::query()->create([
            'name' => 'Partial Cancel Venue',
            'description' => 'Feature test venue',
            'capacity' => 100,
            'price' => $venueDayPrice,
            'wedding_price' => $venueDayPrice,
            'birthday_price' => $venueDayPrice,
            'meeting_staff_price' => $venueDayPrice,
            'status' => Venue::STATUS_AVAILABLE,
        ]);

        $total = $venueDayPrice * $days;
        $booking = Booking::query()->create([
            'guest_id' => $guest->id,
            'check_in' => '2026-05-10 00:00:00',
            'check_out' => '2026-05-12 00:00:00',
            'no_of_days' => $days,
            'total_price' => $total,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_PARTIAL,
            'venue_event_type' => 'wedding',
        ]);

        $booking->venues()->attach($venue->id);

        Payment::query()->create([
            'booking_id' => $booking->id,
            'total_amount' => $total,
            'partial_amount' => $paidAmount,
            'is_fullypaid' => false,
            'provider' => 'cash',
            'provider_ref' => 'test-partial-cancel-'.$booking->id,
            'provider_status' => 'partial',
        ]);

        return $booking->fresh();
    }

    private function createUnpaidReservedVenueBooking(): Booking
    {
        $guest = Guest::query()->create([
            'first_name' => 'Unpaid',
            'middle_name' => null,
            'last_name' => 'Cancel',
            'contact_num' => '09171234568',
            'email' => 'cancel-unpaid@example.test',
            'gender' => Guest::GENDER_OTHER,
        ]);

        $venue = Venue::query()->create([
            'name' => 'Unpaid Cancel Venue',
            'description' => 'Feature test venue',
            'capacity' => 50,
            'price' => 500,
            'wedding_price' => 500,
            'birthday_price' => 500,
            'meeting_staff_price' => 500,
            'status' => Venue::STATUS_AVAILABLE,
        ]);

        $booking = Booking::query()->create([
            'guest_id' => $guest->id,
            'check_in' => '2026-05-10 00:00:00',
            'check_out' => '2026-05-11 00:00:00',
            'no_of_days' => 1,
            'total_price' => 500,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
            'venue_event_type' => 'wedding',
        ]);

        $booking->venues()->attach($venue->id);

        return $booking->fresh();
    }
}
