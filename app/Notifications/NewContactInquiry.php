<?php

namespace App\Notifications;

use App\Filament\Resources\ContactUs\Pages\ContactConversation;
use App\Models\ContactUs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewContactInquiry extends Notification implements ShouldQueue
{
    use Queueable;

    protected ContactUs $contact;

    /**
     * Create a new notification instance.
     */
    public function __construct(ContactUs $contact)
    {
        $this->contact = $contact;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $firstMessage = (string) ($this->contact->message ?: ($this->contact->messages()->oldest()->value('body') ?? ''));

        $conversationUrl = ContactConversation::getUrl(
            ['record' => $this->contact->getKey()],
            panel: 'admin',
        );

        return (new MailMessage)
            ->subject('New Contact Inquiry Received')
            ->greeting('Hello Admin,')
            ->line('A new contact inquiry has been submitted.')
            ->line('**Name:** ' . $this->contact->full_name)
            ->line('**Email:** ' . $this->contact->email)
            ->line('**Phone:** ' . ($this->contact->phone ?? 'Not provided'))
            ->line('**Subject:** ' . $this->contact->subject)
            ->line('**Message:** ' . $firstMessage)
            ->action('View in Admin Panel', $conversationUrl)
            ->line('Please review and respond accordingly.')
            ->salutation('Regards, System Admin');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'contact_id' => $this->contact->id,
            'full_name' => $this->contact->full_name,
            'email' => $this->contact->email,
            'subject' => $this->contact->subject,
        ];
    }
}
