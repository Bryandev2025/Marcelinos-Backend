<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class BookingCreatedNotification extends Notification
{
    public function __construct(public $booking)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Booking Created',
            'body' => "Booking {$this->booking->reference_number} was created.",
            'icon' => 'heroicon-o-calendar-days',
            'color' => 'success',
        ];
    }
}
