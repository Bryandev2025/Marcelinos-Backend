<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyBookingEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $verificationUrl,
        public string $billingToken,
    ) {}

    public function build()
    {
        return $this
            ->subject('Confirm your Marcelino\'s Resort booking')
            ->view('emails.verify-booking', [
                'billingToken' => $this->billingToken,
            ]);
    }
}
