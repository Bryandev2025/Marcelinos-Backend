<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Store a contact form submission.
     * Validates input and returns success. Extend later to store in DB or send email.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email',
            'phone'     => 'nullable|string|max:20',
            'subject'   => 'required|string|max:255',
            'message'   => 'required|string|max:5000',
        ], [
            'full_name.required' => 'Full name is required.',
            'email.required'     => 'Email is required.',
            'email.email'        => 'Please provide a valid email address.',
            'subject.required'   => 'Please select a subject.',
            'message.required'  => 'Message is required.',
        ]);

        // TODO: persist to database or queue email (e.g. Mail::to(config('mail.contact'))->queue(new ContactReceived($validated)));
        return response()->json([
            'success' => true,
            'message' => 'Thank you for your message. We will get back to you soon.',
        ], 200);
    }
}
