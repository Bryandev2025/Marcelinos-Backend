<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class TurnstileVerificationService
{
    public function isConfigured(): bool
    {
        return filled(config('services.turnstile.secret_key'));
    }

    /**
     * @throws ValidationException
     */
    public function verify(string $token, ?string $remoteIp = null): void
    {
        if (! $this->isConfigured()) {
            if (config('app.env') === 'production') {
                throw ValidationException::withMessages([
                    'captcha_token' => ['Verification is not configured.'],
                ]);
            }

            return;
        }

        $token = trim($token);
        if ($token === '') {
            throw ValidationException::withMessages([
                'captcha_token' => ['Please complete the verification challenge.'],
            ]);
        }

        $payload = [
            'secret' => (string) config('services.turnstile.secret_key'),
            'response' => $token,
        ];
        if ($remoteIp !== null && $remoteIp !== '') {
            $payload['remoteip'] = $remoteIp;
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', $payload);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'captcha_token' => ['Verification could not be completed. Please try again.'],
            ]);
        }

        $success = (bool) ($response->json('success') ?? false);
        if (! $success) {
            throw ValidationException::withMessages([
                'captcha_token' => ['Verification failed. Please try again.'],
            ]);
        }
    }
}
