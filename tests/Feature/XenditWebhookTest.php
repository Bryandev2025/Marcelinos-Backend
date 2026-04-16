<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class XenditWebhookTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function paid_webhook_stores_xendit_paid_amount_before_status_update_sync_queue(): void
    {
        Mail::fake();

        $guest = Guest::query()->create([
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Guest',
            'contact_num' => '09171234567',
            'email' => 'test-xendit-webhook@example.test',
            'gender' => Guest::GENDER_OTHER,
        ]);

        $booking = Booking::query()->create([
            'guest_id' => $guest->id,
            'check_in' => now()->addDays(7)->startOfDay(),
            'check_out' => now()->addDays(8)->startOfDay(),
            'no_of_days' => 1,
            'total_price' => 2000.00,
            'status' => Booking::STATUS_UNPAID,
        ]);

        $reference = $booking->reference_number;

        $payload = [
            'id' => 'inv_test_webhook_amount_630',
            'external_id' => $reference,
            'status' => 'PAID',
            'paid_amount' => 630,
            'paid_at' => '2026-04-17T01:34:00.000Z',
            'invoice_url' => 'https://checkout.xendit.co/web/test',
            'metadata' => [
                'payment_mode' => 'partial_30',
                'reference_number' => $reference,
                'receipt_token' => (string) $booking->receipt_token,
            ],
        ];

        $token = (string) env('XENDIT_WEBHOOK_TOKEN');
        $this->assertNotSame('', $token, 'phpunit.xml must set XENDIT_WEBHOOK_TOKEN for this test');

        $response = $this->postJson('/api/xendit/webhook', $payload, [
            'x-callback-token' => $token,
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $booking->refresh();
        $this->assertSame(Booking::STATUS_PARTIAL, $booking->status);

        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('provider', 'xendit')
            ->where('provider_ref', 'inv_test_webhook_amount_630')
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame(630, (int) $payment->partial_amount);
        $this->assertSame(2000, (int) $payment->total_amount);
    }
}
