<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\ContactMessageAppendRequest;
use App\Http\Requests\API\ContactMessageIndexRequest;
use App\Http\Requests\API\ContactRequest;
use App\Jobs\SendContactNotification;
use App\Models\ContactMessage;
use App\Models\ContactUs;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    /**
     * Store a contact form submission.
     * Validates input, stores in database, and notifies administrators.
     */
    public function store(ContactRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $contact = DB::transaction(function () use ($validated): ContactUs {
                $conversation = ContactUs::create([
                    ...$validated,
                    'conversation_token' => Str::random(48),
                ]);

                $message = ContactMessage::create([
                    'contact_us_id' => $conversation->id,
                    'sender_type' => 'client',
                    'sender_name' => $conversation->full_name,
                    'sender_email' => $conversation->email,
                    'body' => (string) $validated['message'],
                    'sent_via' => 'web',
                ]);

                return $conversation;
            });

            SendContactNotification::dispatch($contact);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your message. You can continue this conversation anytime.',
                'conversation' => [
                    'id' => $contact->id,
                    'token' => $contact->conversation_token,
                    'status' => $contact->status,
                    'subject' => $contact->subject,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit contact form',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function messages(ContactMessageIndexRequest $request, int $id): JsonResponse
    {
        $conversation = ContactUs::query()
            ->with(['messages' => fn ($query) => $query->orderBy('created_at')])
            ->findOrFail($id);

        if (! hash_equals((string) $conversation->conversation_token, (string) $request->validated('token'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized conversation token.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status,
                'token' => $conversation->conversation_token,
            ],
            'messages' => $conversation->messages->map(fn (ContactMessage $message): array => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'sender_name' => $message->sender_name,
                'body' => $message->body,
                'sent_via' => $message->sent_via,
                'created_at' => optional($message->created_at)->toIso8601String(),
            ]),
        ]);
    }

    public function appendMessage(ContactMessageAppendRequest $request, int $id): JsonResponse
    {
        $conversation = ContactUs::query()->findOrFail($id);
        $validated = $request->validated();

        if (! hash_equals((string) $conversation->conversation_token, (string) $validated['token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized conversation token.',
            ], 403);
        }

        $message = DB::transaction(function () use ($conversation, $validated): ContactMessage {
            $entry = ContactMessage::create([
                'contact_us_id' => $conversation->id,
                'sender_type' => 'client',
                'sender_name' => $conversation->full_name,
                'sender_email' => $conversation->email,
                'body' => (string) $validated['message'],
                'sent_via' => 'web',
            ]);

            return $entry;
        });

        return response()->json([
            'success' => true,
            'message' => 'Message sent.',
            'data' => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'sender_name' => $message->sender_name,
                'body' => $message->body,
                'sent_via' => $message->sent_via,
                'created_at' => optional($message->created_at)->toIso8601String(),
            ],
        ]);
    }
}
