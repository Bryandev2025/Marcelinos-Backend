<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ContactUs;
use App\Models\User;
use App\Notifications\NewContactInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    /**
     * Store a contact form submission.
     * Validates input, stores in database, and notifies administrators.
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

        // Store the inquiry
        $contact = ContactUs::create($validated);

        // Notify all admin users
        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new NewContactInquiry($contact));

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your message. We will get back to you soon.',
        ], 200);
    }
}
