<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public function build(): self
    {
        $bookingCcAddress = config('mail.booking_cc_address');

        if (filled($bookingCcAddress)) {
            $this->cc($bookingCcAddress);
        }

        return $this
            ->subject('Booking Reminder - Your Stay is Tomorrow')
            ->view('emails.booking-reminder');
    }
}