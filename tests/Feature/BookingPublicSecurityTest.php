<?php

namespace Tests\Feature;

use App\Mail\VerifyBookingEmail;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Venue;
use App\Support\BookingPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingPublicSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // More than five POST /bookings calls per run would hit throttle:bookings (5/min per IP).
        $this->withoutMiddleware(ThrottleRequests::class);
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
    public function identical_booking_rejected(): void
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

        $checkIn = Carbon::parse('2026-05-10')->startOfDay();
        $checkOut = Carbon::parse('2026-05-12')->endOfDay();

        $booking = Booking::query()->create([
            'guest_id' => $guest->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'no_of_days' => 2,
            'total_price' => 1000,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
            'payment_method' => 'cash',
            'online_payment_plan' => '',
            'venue_event_type' => BookingPricing::VENUE_EVENT_WEDDING,
        ]);
        $booking->venues()->attach($venue->id);

        $payload = $this->venueOnlyPayload($venue, 'dup@example.test');

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/bookings', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function same_email_same_dates_different_venue_allowed(): void
    {
        Mail::fake();
        $venueA = $this->createVenue();
        $venueB = Venue::query()->create([
            'name' => 'Security Test Venue B',
            'description' => 'Test',
            'capacity' => 50,
            'price' => 5000,
            'wedding_price' => 5000,
            'birthday_price' => 5000,
            'meeting_staff_price' => 5000,
            'status' => Venue::STATUS_AVAILABLE,
        ]);

        $guest = Guest::query()->create([
            'first_name' => 'Twice',
            'middle_name' => null,
            'last_name' => 'Guest',
            'contact_num' => '09171234567',
            'email' => 'twice@example.test',
            'gender' => Guest::GENDER_OTHER,
        ]);

        $checkIn = Carbon::parse('2026-06-01')->startOfDay();
        $checkOut = Carbon::parse('2026-06-03')->endOfDay();

        $existing = Booking::query()->create([
            'guest_id' => $guest->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'no_of_days' => 2,
            'total_price' => 1000,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
            'payment_method' => 'cash',
            'online_payment_plan' => '',
            'venue_event_type' => BookingPricing::VENUE_EVENT_WEDDING,
        ]);
        $existing->venues()->attach($venueA->id);

        $days = 2;
        $total = BookingPricing::expectedTotalFromRoomLines(
            $days,
            [],
            collect([$venueB]),
            BookingPricing::VENUE_EVENT_WEDDING,
        );

        $payload = [
            'website' => '',
            'check_in' => 'Jun 1, 2026',
            'check_out' => 'Jun 3, 2026',
            'days' => $days,
            'venues' => [$venueB->id],
            'venue_event_type' => BookingPricing::VENUE_EVENT_WEDDING,
            'total_price' => $total,
            'payment_method' => 'cash',
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Booker',
            'email' => 'twice@example.test',
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

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/bookings', $payload)
            ->assertCreated();
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

    #[Test]
    public function foreign_guest_can_book_without_phone_number(): void
    {
        Mail::fake();
        $venue = $this->createVenue();
        $payload = $this->venueOnlyPayload($venue, 'foreign-no-phone@example.test');
        $payload['is_international'] = true;
        $payload['country'] = 'Japan';
        unset($payload['contact_num']);

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/bookings', $payload);

        $response->assertCreated()
            ->assertJsonPath('guest.is_international', true)
            ->assertJsonPath('guest.country', 'Japan')
            ->assertJsonPath('guest.contact_num', '');
    }

    #[Test]
    public function foreign_guest_cannot_set_country_to_philippines(): void
    {
        Mail::fake();
        $venue = $this->createVenue();
        $payload = $this->venueOnlyPayload($venue, 'foreign-ph@example.test');
        $payload['is_international'] = true;
        $payload['country'] = 'Philippines';
        $payload['contact_num'] = '';

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/bookings', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['country']);
    }
}
