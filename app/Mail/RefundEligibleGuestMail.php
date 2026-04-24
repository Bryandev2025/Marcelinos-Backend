<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RefundEligibleGuestMail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public function build(): self
    {
        $booking = $this->booking;
        $booking->loadMissing('guest');

        $guestDisplayName = trim((string) ($booking->guest?->full_name ?? ''));
        if ($guestDisplayName === '') {
            $guestDisplayName = 'Guest';
        }

        $preheader = "We're reviewing your payment for booking {$booking->reference_number} after a change to your reservation.";

        return $this
            ->subject("Marcelino's Resort Hotel - Refund review in progress ({$booking->reference_number})")
            ->view('emails.refund-eligible-guest', [
                'guestDisplayName' => $guestDisplayName,
                'preheader' => $preheader,
            ]);
    }
}
