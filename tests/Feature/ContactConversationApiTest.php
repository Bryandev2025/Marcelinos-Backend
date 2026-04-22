<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\ContactUs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactConversationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_submission_creates_conversation_and_first_message(): void
    {
        $response = $this->withHeaders($this->apiHeaders())->postJson('/api/contact', [
            'full_name' => 'Client Person',
            'email' => 'client@example.com',
            'phone' => '09171234567',
            'subject' => 'Booking Inquiry',
            'message' => 'Hello, I want to ask about weekend availability.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('conversation.id', 1);

        $this->assertDatabaseHas('contact_us', [
            'id' => 1,
            'email' => 'client@example.com',
        ]);

        $this->assertDatabaseHas('contact_messages', [
            'contact_us_id' => 1,
            'sender_type' => 'client',
            'sent_via' => 'web',
        ]);

    }

    public function test_client_can_fetch_and_append_message_with_valid_token(): void
    {
        $conversation = ContactUs::query()->create([
            'full_name' => 'Client Person',
            'email' => 'client@example.com',
            'phone' => null,
            'subject' => 'Event Inquiry',
            'message' => 'Initial message',
            'status' => 'new',
            'conversation_token' => 'valid-token',
        ]);

        ContactMessage::query()->create([
            'contact_us_id' => $conversation->id,
            'sender_type' => 'client',
            'sender_name' => $conversation->full_name,
            'sender_email' => $conversation->email,
            'body' => 'Initial message',
            'sent_via' => 'web',
        ]);

        $messagesResponse = $this->withHeaders($this->apiHeaders())
            ->getJson("/api/contact/{$conversation->id}/messages?token=valid-token");

        $messagesResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('conversation.id', $conversation->id);

        $appendResponse = $this->withHeaders($this->apiHeaders())
            ->postJson("/api/contact/{$conversation->id}/messages", [
                'token' => 'valid-token',
                'message' => 'Follow-up from client',
            ]);

        $appendResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sender_type', 'client');

        $this->assertDatabaseHas('contact_messages', [
            'contact_us_id' => $conversation->id,
            'body' => 'Follow-up from client',
            'sender_type' => 'client',
        ]);

    }

    public function test_client_cannot_access_messages_with_invalid_token(): void
    {
        $conversation = ContactUs::query()->create([
            'full_name' => 'Client Person',
            'email' => 'client@example.com',
            'phone' => null,
            'subject' => 'Event Inquiry',
            'message' => 'Initial message',
            'status' => 'new',
            'conversation_token' => 'valid-token',
        ]);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson("/api/contact/{$conversation->id}/messages?token=wrong-token");

        $response->assertForbidden();
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        config()->set('services.api.key', 'test-api-key');

        return [
            'x-api-key' => 'test-api-key',
            'Accept' => 'application/json',
        ];
    }
}
