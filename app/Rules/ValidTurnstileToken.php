<?php

namespace App\Rules;

use App\Services\TurnstileVerificationService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\ValidationException;

class ValidTurnstileToken implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $service = app(TurnstileVerificationService::class);

        if (! $service->isConfigured()) {
            return;
        }

        try {
            $service->verify(is_string($value) ? $value : '', request()->ip());
        } catch (ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();
            $fail(is_string($first) ? $first : 'Verification failed.');
        }
    }
}
