<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\Slack\XenditWebhookFailureSlackNotification;
use App\Support\SlackBookingAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    private const DEBUG_CACHE_KEY = 'xendit_webhook_last_event';

    public function handle(Request $request): JsonResponse
    {
        $configuredToken = trim((string) env('XENDIT_WEBHOOK_TOKEN', ''));
        $callbackToken = trim((string) $request->header('x-callback-token', ''));
        $payload = $request->all();

        if ($configuredToken === '' || ! hash_equals($configuredToken, $callbackToken)) {
            $this->storeDebugSnapshot($payload, [
                'result' => 'rejected',
                'reason' => 'invalid_callback_token',
                'status' => strtoupper((string) ($payload['status'] ?? '')),
                'booking_reference' => null,
                'booking_receipt_token' => null,
            ]);

            SlackBookingAlerts::notify(new XenditWebhookFailureSlackNotification('invalid_callback_token', $payload));

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized webhook request.',
            ], 401);
        }

        $status = strtoupper((string) ($payload['status'] ?? ''));
        $isPaidEvent = in_array($status, ['PAID', 'SETTLED'], true);
        $invoiceId = (string) ($payload['id'] ?? '');
        $paidAmount = $this->toNumericAmount($payload['paid_amount'] ?? null);
        $eventKey = $this->buildEventKey($invoiceId, $status, $paidAmount, (string) ($payload['paid_at'] ?? ''));

        if ($this->isDuplicateEvent($eventKey)) {
            return response()->json([
                'success' => true,
                'message' => 'Duplicate webhook skipped.',
            ]);
        }

        if (! $isPaidEvent) {
            $this->recordWebhookEvent($eventKey, $payload);
            $this->storeDebugSnapshot($payload, [
                'result' => 'ignored',
                'reason' => 'unsupported_status',
                'status' => $status,
                'booking_reference' => null,
                'booking_receipt_token' => null,
            ]);

            if (SlackBookingAlerts::nonpaidXenditWebhookAlertsEnabled()) {
                SlackBookingAlerts::notify(new XenditWebhookFailureSlackNotification('unsupported_status', $payload));
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook acknowledged.',
            ]);
        }

        $booking = $this->resolveBookingFromPayload($payload);

        if (! $booking) {
            Log::warning('Xendit webhook booking not found', [
                'external_id' => $payload['external_id'] ?? null,
                'invoice_id' => $payload['id'] ?? null,
                'status' => $status,
            ]);
            $this->recordWebhookEvent($eventKey, $payload);
            $this->storeDebugSnapshot($payload, [
                'result' => 'failed',
                'reason' => 'booking_not_found',
                'status' => $status,
                'booking_reference' => null,
                'booking_receipt_token' => null,
            ]);

            SlackBookingAlerts::notify(new XenditWebhookFailureSlackNotification('booking_not_found', $payload));

            return response()->json([
                'success' => false,
                'message' => 'Booking not found for webhook payload.',
            ], 404);
        }

        if (in_array((string) $booking->stay_status, [Booking::STAY_STATUS_CANCELLED, Booking::STAY_STATUS_COMPLETED], true)) {
            $this->recordWebhookEvent($eventKey, $payload);
            $this->storeDebugSnapshot($payload, [
                'result' => 'ignored',
                'reason' => 'booking_final_state',
                'status' => $status,
                'booking_reference' => $booking->reference_number,
                'booking_receipt_token' => $booking->receipt_token,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Booking state is final; no status change applied.',
            ]);
        }

        $bookingTotal = $this->toNumericAmount($booking->total_price);

        $nextPaymentStatus = ($paidAmount >= $bookingTotal && $bookingTotal > 0)
            ? Booking::PAYMENT_STATUS_PAID
            : Booking::PAYMENT_STATUS_PARTIAL;

        $invoiceUrl = (string) ($payload['invoice_url'] ?? '');
        $paymentMode = (string) ($payload['metadata']['payment_mode'] ?? '');

        $updateFields = [];
        if ($invoiceId !== '' && empty($booking->xendit_invoice_id)) {
            $updateFields['xendit_invoice_id'] = $invoiceId;
        }
        if ($invoiceUrl !== '' && empty($booking->xendit_invoice_url)) {
            $updateFields['xendit_invoice_url'] = $invoiceUrl;
        }
        if ($paymentMode !== '' && empty($booking->online_payment_plan)) {
            $updateFields['online_payment_plan'] = $paymentMode;
        }
        if (empty($booking->payment_method)) {
            $updateFields['payment_method'] = 'online';
        }

        if ($updateFields !== []) {
            $booking->update($updateFields);
        }

        // Persist payment + webhook event before status change so Slack/observers see Payment rows
        // when the queue runs synchronously.
        $this->upsertProviderPaymentRecord($booking, $invoiceId, $status, $paidAmount, $bookingTotal);
        $this->recordWebhookEvent($eventKey, $payload);
        Cache::forget($this->pendingOnlinePaymentCacheKey((int) $booking->id));

        if ((string) $booking->payment_status !== $nextPaymentStatus) {
            $booking->update(['payment_status' => $nextPaymentStatus]);
        }
        $freshBooking = $booking->fresh();
        $this->storeDebugSnapshot($payload, [
            'result' => 'processed',
            'reason' => 'status_updated',
            'status' => $status,
            'booking_reference' => $freshBooking?->reference_number,
            'booking_receipt_token' => $freshBooking?->receipt_token,
            'booking_status' => $freshBooking?->status,
            'payment_status' => $freshBooking?->payment_status,
            'stay_status' => $freshBooking?->stay_status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed successfully.',
            'data' => [
                'reference_number' => $freshBooking?->reference_number,
                'receipt_token' => $freshBooking?->receipt_token,
                // legacy field retained for client compatibility
                'status' => $freshBooking?->status,
                'payment_status' => $freshBooking?->payment_status,
                'stay_status' => $freshBooking?->stay_status,
            ],
        ]);
    }

    private function resolveBookingFromPayload(array $payload): ?Booking
    {
        $candidates = array_values(array_filter([
            (string) ($payload['external_id'] ?? ''),
            (string) ($payload['reference_number'] ?? ''),
            (string) ($payload['receipt_token'] ?? ''),
            (string) ($payload['metadata']['reference_number'] ?? ''),
            (string) ($payload['metadata']['receipt_token'] ?? ''),
        ]));

        if ($candidates === []) {
            return null;
        }

        return Booking::query()
            ->where(function ($query) use ($candidates): void {
                $query->whereIn('reference_number', $candidates)
                    ->orWhereIn('receipt_token', $candidates);
            })
            ->first();
    }

    private function toNumericAmount(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            return (float) preg_replace('/[^0-9.\-]/', '', $value);
        }

        return 0.0;
    }

    private function pendingOnlinePaymentCacheKey(int $bookingId): string
    {
        return "booking_online_payment_pending_{$bookingId}";
    }

    private function buildEventKey(string $invoiceId, string $status, float $paidAmount, string $paidAt): string
    {
        return sha1(implode('|', [$invoiceId, $status, number_format($paidAmount, 2, '.', ''), $paidAt]));
    }

    private function isDuplicateEvent(string $eventKey): bool
    {
        return DB::table('xendit_webhook_events')->where('event_key', $eventKey)->exists();
    }

    private function recordWebhookEvent(string $eventKey, array $payload): void
    {
        DB::table('xendit_webhook_events')->insertOrIgnore([
            'event_key' => $eventKey,
            'invoice_id' => (string) ($payload['id'] ?? ''),
            'external_id' => (string) ($payload['external_id'] ?? ''),
            'status' => (string) ($payload['status'] ?? ''),
            'paid_amount' => $this->toNumericAmount($payload['paid_amount'] ?? null),
            'received_at' => Carbon::now(),
            'payload' => json_encode($payload),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function upsertProviderPaymentRecord(
        Booking $booking,
        string $invoiceId,
        string $providerStatus,
        float $paidAmount,
        float $bookingTotal
    ): void {
        if ($invoiceId === '' || $paidAmount <= 0) {
            return;
        }

        $partialAmount = (int) round($paidAmount);
        $totalAmount = (int) round($bookingTotal);
        $isFullyPaid = $paidAmount >= $bookingTotal && $bookingTotal > 0;

        $existing = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('provider', 'xendit')
            ->where('provider_ref', $invoiceId)
            ->first();

        if ($existing) {
            $existing->update([
                'total_amount' => $totalAmount,
                'partial_amount' => $partialAmount,
                'is_fullypaid' => $isFullyPaid,
                'provider_status' => strtolower($providerStatus),
            ]);

            return;
        }

        $booking->payments()->create([
            'total_amount' => $totalAmount,
            'partial_amount' => $partialAmount,
            'is_fullypaid' => $isFullyPaid,
            'provider' => 'xendit',
            'provider_ref' => $invoiceId,
            'provider_status' => strtolower($providerStatus),
        ]);
    }

    private function storeDebugSnapshot(array $payload, array $result): void
    {
        Cache::forever(self::DEBUG_CACHE_KEY, [
            'received_at' => now()->toIso8601String(),
            'invoice_id' => (string) ($payload['id'] ?? ''),
            'external_id' => (string) ($payload['external_id'] ?? ''),
            'paid_amount' => $payload['paid_amount'] ?? null,
            'raw_status' => (string) ($payload['status'] ?? ''),
            'result' => $result['result'] ?? 'unknown',
            'reason' => $result['reason'] ?? null,
            'booking_reference' => $result['booking_reference'] ?? null,
            'booking_receipt_token' => $result['booking_receipt_token'] ?? null,
            'booking_status' => $result['booking_status'] ?? null,
        ]);
    }
}
