<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingStatementAccessTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_403_when_token_is_missing(): void
    {
        Mail::fake();
        Storage::fake('public');

        $booking = $this->createReservedPaidVenueBooking();

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson("/api/billing/{$booking->id}");

        $response->assertStatus(403)
            ->assertJsonPath('error', 'billing_token_invalid');
    }

    #[Test]
    public function it_returns_403_when_token_is_invalid(): void
    {
        Mail::fake();
        Storage::fake('public');

        $booking = $this->createReservedPaidVenueBooking();

        $booking->refresh();
        $rawToken = $booking->generateBillingAccessToken();

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson("/api/billing/{$booking->id}?token=not-the-token");

        $response->assertStatus(403)
            ->assertJsonPath('error', 'billing_token_invalid');

        // Keep unused variable warning away.
        $this->assertNotSame('', $rawToken);
    }

    #[Test]
    public function it_returns_403_when_token_is_expired(): void
    {
        Mail::fake();
        Storage::fake('public');

        $booking = $this->createReservedPaidVenueBooking();
        $rawToken = $booking->generateBillingAccessToken();

        $booking->forceFill(['token_expires_at' => now()->subMinute()])->saveQuietly();

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson("/api/billing/{$booking->id}?token={$rawToken}");

        $response->assertStatus(403)
            ->assertJsonPath('error', 'token_expired');
    }

    #[Test]
    public function it_allows_access_with_valid_token(): void
    {
        Mail::fake();
        Storage::fake('public');

        $booking = $this->createReservedPaidVenueBooking();
        $rawToken = $booking->generateBillingAccessToken();

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson("/api/billing/{$booking->id}?token={$rawToken}");

        $response->assertOk();
        $response->assertJsonPath('booking.reference_number', (string) $booking->reference_number);
    }

    private function createReservedPaidVenueBooking(): Booking
    {
        $guest = Guest::query()->create([
            'first_name' => 'Billing',
            'middle_name' => null,
            'last_name' => 'Guest',
            'contact_num' => '09991234567',
            'email' => 'billing@example.test',
            'gender' => Guest::GENDER_OTHER,
        ]);

        $venue = Venue::query()->create([
            'name' => 'Billing Test Venue',
            'description' => 'Feature test venue',
            'capacity' => 100,
            'price' => 1000,
            'wedding_price' => 1000,
            'birthday_price' => 1000,
            'meeting_staff_price' => 1000,
            'status' => Venue::STATUS_AVAILABLE,
        ]);

        $booking = Booking::query()->create([
            'guest_id' => $guest->id,
            'check_in' => '2026-05-10 00:00:00',
            'check_out' => '2026-05-12 00:00:00',
            'no_of_days' => 2,
            'total_price' => 2000,
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_PAID,
            'venue_event_type' => 'wedding',
        ]);

        $booking->venues()->attach($venue->id);

        Payment::query()->create([
            'booking_id' => $booking->id,
            'total_amount' => 2000,
            'partial_amount' => 2000,
            'is_fullypaid' => true,
            'provider' => 'cash',
            'provider_ref' => 'test-billing-paid-'.$booking->id,
            'provider_status' => 'paid',
        ]);

        return $booking->fresh();
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
}

