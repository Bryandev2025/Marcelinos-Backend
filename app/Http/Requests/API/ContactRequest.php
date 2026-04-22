<?php

namespace App\Http\Requests\API;

use App\Rules\ValidTurnstileToken;
use App\Services\TurnstileVerificationService;
use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $turnstile = app(TurnstileVerificationService::class);
        $captchaRules = array_values(array_filter([
            $turnstile->isConfigured() ? 'required' : 'nullable',
            'string',
            new ValidTurnstileToken,
        ]));

        return [
            'website' => ['nullable', 'string', 'max:0'],
            'captcha_token' => $captchaRules,
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email',
            'phone'     => 'nullable|string|max:20',
            'subject'   => 'required|string|max:255',
            'message'   => 'required|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Full name is required.',
            'email.required'     => 'Email is required.',
            'email.email'        => 'Please provide a valid email address.',
            'subject.required'   => 'Please select a subject.',
            'message.required'   => 'Message is required.',
        ];
    }
}
