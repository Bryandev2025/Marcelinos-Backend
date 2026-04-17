<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SemaphoreSmsService
{
    public function sendBookingReminder(string $rawPhoneNumber, string $message): string
    {
        $apiKey = trim((string) config('services.semaphore.api_key'));
        $senderName = trim((string) config('services.semaphore.sender_name'));
        $messagesUrl = trim((string) config('services.semaphore.messages_url', 'https://api.semaphore.co/api/v4/messages'));

        if ($apiKey === '') {
            throw new RuntimeException('Semaphore API key is not configured.');
        }

        if ($senderName === '') {
            throw new RuntimeException('Semaphore sender name is not configured.');
        }

        $number = $this->normalizePhilippineNumber($rawPhoneNumber);
        if ($number === null) {
            throw new RuntimeException('Guest phone number is invalid for SMS.');
        }

        $response = Http::asForm()
            ->timeout(15)
            ->post($messagesUrl, [
                'apikey' => $apiKey,
                'number' => $number,
                'message' => $message,
                'sendername' => $senderName,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Semaphore SMS request failed: '.$response->status().' '.$response->body());
        }

        $payload = $response->json();
        if (! is_array($payload) || $payload === []) {
            throw new RuntimeException('Semaphore SMS response payload is empty.');
        }

        $first = $payload[0] ?? null;
        if (! is_array($first)) {
            throw new RuntimeException('Semaphore SMS response format is invalid.');
        }

        if (isset($first['status']) && (string) $first['status'] === 'Failed') {
            $error = isset($first['message']) ? (string) $first['message'] : 'Unknown SMS failure';
            throw new RuntimeException('Semaphore SMS failed: '.$error);
        }

        return $number;
    }

    private function normalizePhilippineNumber(string $rawPhoneNumber): ?string
    {
        $digits = preg_replace('/\D+/', '', $rawPhoneNumber);
        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '09') && strlen($digits) === 11) {
            return '63'.substr($digits, 1);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '63'.$digits;
        }

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return $digits;
        }

        return null;
    }
}
