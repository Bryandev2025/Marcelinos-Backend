<?php

namespace Tests\Feature;

use App\Mail\VerifyBookingEmail;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Venue;
use App\Support\BookingPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingPublicSecurityTest extends TestCase
{
    use RefreshDatabase;

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

    private function venueOnlyPayload(Venue $venue, string $email = 'booker@example.test'): array
    {
        $days = 2;
        $total = BookingPricing::expectedTotalFromRoomLines(
            $days,
            [],
            collect([$venue]),
            BookingPricing::VENUE_EVENT_WEDDING,
        );

        return [
            'website' => '',
            'check_in' => 'May 10, 2026',
            'check_out' => 'May 12, 2026',
            'days' => $days,
            'venues' => [$venue->id],
            'venue_event_type' => BookingPricing::VENUE_EVENT_WEDDING,
            'total_price' => $total,
            'payment_method' => 'cash',
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Booker',
            'email' => $email,
            'contact_num' => '09171234567',
            'gender' => Guest::GENDER_OTHER,
            'is_international' => false,
            'street' => '',
            'address' => 'Test',
            'zip_code' => '',
            'category' => '',
            'newsletter' => false,
            'notifications' => false,
        ];
    }

    private function createVenue(): Venue
    {
        return Venue::query()->create([
            'name' => 'Security Test Venue',
            'description' => 'Test',
            'capacity' => 50,
            'price' => 5000,
            'wedding_price' => 5000,
            'birthday_price' => 5000,
            'meeting_staff_price' => 5000,
            'status' => Venue::STATUS_AVAILABLE,
        ]);
    }

    #[Test]
    public function honeypot_website_field_rejects_non_empty_value(): void
    {
        Mail::fake();
        $venue = $this->createVenue();
        $payload = $this->venueOnlyPayload($venue);
        $payload['website'] = 'https://spam.example';

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/bookings', $payload)
            ->assertStatus(422);
    }

    #[Test]
    public function booking_accepts_request_without_captcha_token_when_turnstile_configured(): void
    {
        Mail::fake();
        config()->set('services.turnstile.secret_key', 'ts-secret-test');

        $venue = $this->createVenue();
        $payload = $this->venueOnlyPayload($venue);

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/bookings', $payload);

        $response->assertCreated()
            ->assertJsonPath('email_verification_required', true)
            ->assertJsonPath('booking.booking_status', Booking::BOOKING_STATUS_PENDING_VERIFICATION);

        Mail::assertQueued(VerifyBookingEmail::class);
    }

    #[Test]
    public function duplicate_overlapping_email_rejected(): void
    {
        Mail::fake();
        $venue = $this->createVenue();
        $guest = Guest::query()->create([
            'first_name' => 'Dup',
            'middle_name' => null,
            'last_name' => 'Guest',
            'contact_num' => '09171234567',
            'email' => 'dup@example.test',
            'gender' => Guest::GENDER_OTHER,
        ]);

        Booking::query()->create([
            'guest_id' => $guest->id,
            'check_in' => '2026-05-10 12:00:00',
            'check_out' => '2026-05-12 10:00:00',
            'no_of_days' => 2,
            'total_price' => 1000,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
            'venue_event_type' => BookingPricing::VENUE_EVENT_WEDDING,
        ]);

        $payload = $this->venueOnlyPayload($venue, 'dup@example.test');

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/bookings', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function signed_verify_email_confirms_booking(): void
    {
        Mail::fake();
        $venue = $this->createVenue();
        $guest = Guest::query()->create([
            'first_name' => 'V',
            'middle_name' => null,
            'last_name' => 'Guest',
            'contact_num' => '09171234567',
            'email' => 'verify@example.test',
            'gender' => Guest::GENDER_OTHER,
        ]);

        $booking = Booking::query()->create([
            'guest_id' => $guest->id,
            'check_in' => '2026-05-10 00:00:00',
            'check_out' => '2026-05-12 23:59:59',
            'no_of_days' => 2,
            'total_price' => 10000,
            'booking_status' => Booking::BOOKING_STATUS_PENDING_VERIFICATION,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
            'payment_method' => 'cash',
            'venue_event_type' => BookingPricing::VENUE_EVENT_WEDDING,
        ]);
        $booking->venues()->attach($venue->id);

        $url = URL::temporarySignedRoute(
            'bookings.verify-email',
            now()->addHour(),
            ['booking' => $booking->id],
        );

        $this->get($url)->assertRedirect();

        $booking->refresh();
        $this->assertSame(Booking::BOOKING_STATUS_RESERVED, (string) $booking->booking_status);
        $this->assertNotNull($booking->email_verified_at);
    }
}
