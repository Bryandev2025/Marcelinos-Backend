<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\ForgotPasswordRequest;
use App\Notifications\ApiResetPasswordNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');

        try {
            Password::broker()->sendResetLink(['email' => $email], function ($user, string $token) use ($email) {
                $user->notify(new ApiResetPasswordNotification($token, $email));
            });
        } catch (\Throwable $e) {
            // Avoid leaking whether a user exists and keep a consistent UX.
            return response()->json([
                'success' => false,
                'message' => 'Unable to send reset link at this time.',
            ], 500);
        }

        // Always return success to avoid user enumeration.
        return response()->json([
            'success' => true,
            'message' => 'If an account with that email exists, a reset link has been sent.',
        ], 200);
    }
}

