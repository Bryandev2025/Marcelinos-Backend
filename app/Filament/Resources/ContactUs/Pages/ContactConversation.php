<?php

namespace App\Filament\Resources\ContactUs\Pages;

use App\Filament\Resources\ContactUs\ContactUsResource;
use App\Mail\ContactReply;
use App\Models\ContactMessage;
use App\Models\ContactUs;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactConversation extends Page
{
    protected static string $resource = ContactUsResource::class;

    protected string $view = 'filament.resources.contact-us.pages.contact-conversation';

    public ContactUs $record;

    public string $replyMessage = '';

    public string $status = 'new';

    /**
     * @var array{total_messages: int, started_at: string|null, last_message_at: string|null, replied_at: string|null}
     */
    public array $threadMeta = [
        'total_messages' => 0,
        'started_at' => null,
        'last_message_at' => null,
        'replied_at' => null,
    ];

    public function mount(ContactUs|int|string $record): void
    {
        $conversation = $this->resolveConversation($record);

        if (! $conversation) {
            $requested = $record instanceof ContactUs
                ? (string) $record->getKey()
                : (string) $record;

            Log::warning('Contact conversation not found', [
                'record' => $requested,
                'user_id' => auth()->id(),
            ]);

            Notification::make()
                ->title('Conversation not found. It may have been removed or the link is outdated.')
                ->body($requested !== '' ? 'Requested: ' . $requested : null)
                ->warning()
                ->send();

            $this->redirectToIndex();

            return;
        }

        $conversation->load(['messages' => fn ($query) => $query->orderBy('created_at')->orderBy('id')]);

        abort_unless(ContactUsResource::canView($conversation), 403);

        $this->record = $conversation;
        $this->status = (string) $conversation->status;
        $this->syncThreadMeta();
    }

    public function refreshConversation(): void
    {
        if (! isset($this->record)) {
            return;
        }

        $this->record->load(['messages' => fn ($query) => $query->orderBy('created_at')->orderBy('id')]);
        $this->syncThreadMeta();
    }

    public function updateStatus(): void
    {
        $allowed = ['new', 'in_progress', 'resolved', 'closed'];

        if (! in_array($this->status, $allowed, true)) {
            return;
        }

        $this->record->update(['status' => $this->status]);
        $this->record->refresh();

        Notification::make()
            ->title('Conversation status updated.')
            ->success()
            ->send();
    }

    public function sendReply(): void
    {
        $body = trim($this->replyMessage);

        if ($body === '') {
            Notification::make()
                ->title('Reply message is required.')
                ->danger()
                ->send();

            return;
        }

        $message = ContactMessage::create([
            'contact_us_id' => $this->record->id,
            'sender_type' => 'admin',
            'sender_name' => auth()->user()?->name ?? 'Admin',
            'sender_email' => auth()->user()?->email,
            'body' => $body,
            'sent_via' => 'admin_panel',
        ]);

        Mail::to($this->record->email)->send(new ContactReply($this->record, $body));

        $this->record->update([
            'status' => $this->record->status === 'new' ? 'in_progress' : $this->record->status,
            'replied_at' => now(),
        ]);

        $this->replyMessage = '';
        $this->record->refresh();
        $this->record->load(['messages' => fn ($query) => $query->orderBy('created_at')->orderBy('id')]);
        $this->status = (string) $this->record->status;
        $this->syncThreadMeta();

        Notification::make()
            ->title('Reply sent and conversation updated.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to list')
                ->color('gray')
                ->url(ContactUsResource::getUrl('index')),
            Action::make('edit')
                ->label('Edit fields')
                ->url(ContactUsResource::getUrl('edit', ['record' => (string) $this->record->getKey()])),
        ];
    }

    private function syncThreadMeta(): void
    {
        $messages = $this->record->messages;
        $firstMessage = $messages->first();
        $lastMessage = $messages->last();

        $this->threadMeta = [
            'total_messages' => $messages->count(),
            'started_at' => $firstMessage?->created_at?->format('M d, Y h:i A'),
            'last_message_at' => $lastMessage?->created_at?->format('M d, Y h:i A'),
            'replied_at' => $this->record->replied_at?->format('M d, Y h:i A'),
        ];
    }

    private function resolveConversation(ContactUs|int|string $record): ?ContactUs
    {
        if ($record instanceof ContactUs) {
            return $record;
        }

        $raw = trim(rawurldecode((string) $record));

        if ($raw === '') {
            return null;
        }

        $recordId = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (is_int($recordId)) {
            $byId = ContactUs::query()
                ->withTrashed()
                ->whereKey($recordId)
                ->first();

            if ($byId instanceof ContactUs) {
                return $byId;
            }
        }

        return ContactUs::query()
            ->withTrashed()
            ->where('conversation_token', $raw)
            ->first();
    }

    private function redirectToIndex(): void
    {
        $this->redirect(ContactUsResource::getUrl('index'), navigate: true);
        $this->skipRender();
    }
}
