<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactUs\ContactUsResource;
use App\Filament\Resources\ContactUs\Pages\ContactConversation;
use App\Models\ContactMessage;
use App\Models\ContactUs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ContactConversationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_record_redirects_back_to_contact_list(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(ContactConversation::class, ['record' => 'invalid-record'])
            ->assertRedirect('/admin/contact-us');
    }

    public function test_soft_deleted_conversation_can_still_be_opened_by_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $conversation = ContactUs::query()->create([
            'full_name' => 'Deleted Client',
            'email' => 'deleted-client@example.com',
            'phone' => null,
            'subject' => 'Thread test',
            'message' => 'Initial message',
            'status' => 'new',
            'conversation_token' => 'conversation-token-a',
        ]);

        ContactMessage::query()->create([
            'contact_us_id' => $conversation->id,
            'sender_type' => 'client',
            'sender_name' => $conversation->full_name,
            'sender_email' => $conversation->email,
            'body' => 'Initial message',
            'sent_via' => 'web',
        ]);

        $conversation->delete();

        Livewire::actingAs($admin)
            ->test(ContactConversation::class, ['record' => (string) $conversation->id])
            ->assertSet('record.id', $conversation->id)
            ->assertSet('threadMeta.total_messages', 1);
    }

    public function test_admin_reply_appends_threaded_message_and_updates_status(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $conversation = ContactUs::query()->create([
            'full_name' => 'Thread Client',
            'email' => 'thread-client@example.com',
            'phone' => null,
            'subject' => 'Threaded exchange',
            'message' => 'Hello',
            'status' => 'new',
            'conversation_token' => 'conversation-token-b',
        ]);

        ContactMessage::query()->create([
            'contact_us_id' => $conversation->id,
            'sender_type' => 'client',
            'sender_name' => $conversation->full_name,
            'sender_email' => $conversation->email,
            'body' => 'Hello',
            'sent_via' => 'web',
        ]);

        Livewire::actingAs($admin)
            ->test(ContactConversation::class, ['record' => (string) $conversation->id])
            ->set('replyMessage', 'Admin follow-up')
            ->call('sendReply')
            ->assertSet('status', 'in_progress')
            ->assertSet('threadMeta.total_messages', 2);

        $this->assertDatabaseHas('contact_messages', [
            'contact_us_id' => $conversation->id,
            'sender_type' => 'admin',
            'body' => 'Admin follow-up',
            'sent_via' => 'admin_panel',
        ]);
    }

    public function test_conversation_resolvable_by_conversation_token(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $conversation = ContactUs::query()->create([
            'full_name' => 'Token Client',
            'email' => 'token-client@example.com',
            'phone' => null,
            'subject' => 'Token lookup',
            'message' => 'Hello via token',
            'status' => 'new',
            'conversation_token' => 'token-lookup-xyz',
        ]);

        ContactMessage::query()->create([
            'contact_us_id' => $conversation->id,
            'sender_type' => 'client',
            'sender_name' => $conversation->full_name,
            'sender_email' => $conversation->email,
            'body' => 'Hello via token',
            'sent_via' => 'web',
        ]);

        Livewire::actingAs($admin)
            ->test(ContactConversation::class, ['record' => 'token-lookup-xyz'])
            ->assertSet('record.id', $conversation->id)
            ->assertSet('threadMeta.total_messages', 1);
    }

    public function test_conversation_url_from_resource_uses_expected_path(): void
    {
        $conversation = ContactUs::query()->create([
            'full_name' => 'Url Client',
            'email' => 'url-client@example.com',
            'phone' => null,
            'subject' => 'Url check',
            'message' => 'Hi',
            'status' => 'new',
            'conversation_token' => 'conversation-token-url',
        ]);

        $url = ContactUsResource::getUrl(
            'conversation',
            ['record' => $conversation],
            panel: 'admin',
        );

        $this->assertStringEndsWith('/admin/contact-us/' . $conversation->getKey() . '/conversation', $url);
    }
}
