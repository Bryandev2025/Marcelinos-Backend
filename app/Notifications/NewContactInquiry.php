<?php

namespace App\Notifications;

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
        return (new MailMessage)
            ->subject('New Contact Inquiry Received')
            ->greeting('Hello Admin,')
            ->line('A new contact inquiry has been submitted.')
            ->line('**Name:** ' . $this->contact->full_name)
            ->line('**Email:** ' . $this->contact->email)
            ->line('**Phone:** ' . ($this->contact->phone ?? 'Not provided'))
            ->line('**Subject:** ' . $this->contact->subject)
            ->line('**Message:** ' . $this->contact->message)
            ->action('View in Admin Panel', url('/admin/contact-us/' . $this->contact->id))
            ->line('Please review and respond accordingly.');
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
