<?php

namespace App\Jobs;

use App\Filament\Resources\ContactUs\Pages\ContactConversation;
use App\Models\ContactUs;
use App\Models\User;
use App\Notifications\NewContactInquiry;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendContactNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ContactUs $contact
    ) {}

    public function handle(): void
    {
        $users = User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->where('is_active', true)
            ->get();

        // Keep email notifications for admins (external alert).
        $admins = $users->where('role', 'admin')->values();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NewContactInquiry($this->contact));
        }

        // Add in-app (bell) notifications for active staff + admins.
        $conversationUrl = ContactConversation::getUrl(
            ['record' => $this->contact->getKey()],
            panel: 'admin',
        );

        foreach ($users as $user) {
            FilamentNotification::make()
                ->title('New Contact Inquiry')
                ->body("{$this->contact->full_name} sent a message: {$this->contact->subject}")
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('warning')
                ->actions([
                    Action::make('view')
                        ->label('Open')
                        ->button()
                        ->url($conversationUrl),
                ])
                ->sendToDatabase($user)
                ->broadcast($user);
        }
    }
}
