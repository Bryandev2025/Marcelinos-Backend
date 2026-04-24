<?php

namespace App\Mail;

use App\Models\ContactUs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactReply extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ContactUs $contact;

    public string $replyMessage;

    /**
     * Create a new message instance.
     */
    public function __construct(ContactUs $contact, string $replyMessage)
    {
        $this->contact = $contact;
        $this->replyMessage = $replyMessage;
    }

    /**
     * Guest link to the SPA contact section, resuming this thread.
     */
    public function continueConversationUrl(): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $query = http_build_query([
            'contact' => $this->contact->getKey(),
            'token' => (string) $this->contact->conversation_token,
        ], '', '&', PHP_QUERY_RFC3986);

        return $base.'/?'.$query.'#contact';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Re: '.$this->contact->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact-reply',
            with: [
                'contact' => $this->contact,
                'replyMessage' => $this->replyMessage,
                'continueConversationUrl' => $this->continueConversationUrl(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
