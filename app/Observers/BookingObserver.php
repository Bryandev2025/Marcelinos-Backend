<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Notifications\BookingCreatedNotification;
use Filament\Notifications\Notification;

class BookingObserver
{
    /**
     * Handle the Booking "created" event.
     */
    public function created(Booking $booking): void
    {
        $recipient = auth()->user();

        Notification::make()
        ->title('Saved successfully')
        ->sendToDatabase($recipient);

    }

    /**
     * Handle the Booking "updated" event.
     */
    public function updated(Booking $booking): void
    {
        //
    }

    /**
     * Handle the Booking "deleted" event.
     */
    public function deleted(Booking $booking): void
    {
        //
    }

    /**
     * Handle the Booking "restored" event.
     */
    public function restored(Booking $booking): void
    {
        //
    }

    /**
     * Handle the Booking "force deleted" event.
     */
    public function forceDeleted(Booking $booking): void
    {
        //
    }
}
