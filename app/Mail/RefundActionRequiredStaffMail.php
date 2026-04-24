<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RefundActionRequiredStaffMail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;

    public string $bookingAdminUrl;

    public function __construct(Booking $booking, string $bookingAdminUrl)
    {
        $this->booking = $booking;
        $this->bookingAdminUrl = $bookingAdminUrl;
    }

    public function build(): self
    {
        return $this
            ->subject("Marcelino's Resort Hotel - Refund action required")
            ->view('emails.refund-action-required-staff');
    }
}
