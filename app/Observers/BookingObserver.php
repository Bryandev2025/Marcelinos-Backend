<?php

namespace App\Observers;

use Filament\Notifications\Notification;
use App\Models\User;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;

class BookingObserver
{
    public function created(Booking $booking): void
    {
        Log::info('BookingObserver triggered for booking: ' . $booking->id . ' with reference: ' . $booking->reference_number);

        $users = User::whereIn('role', ['admin', 'staff'])
            ->where('is_active', true)
            ->get();

        Log::info('Users found for notification: ' . $users->count());

        if ($users->isNotEmpty()) {
            Log::info('Sending notification to users');
            foreach ($users as $user) {
                Notification::make()
                    ->title('New Booking Created')
                    ->body("Booking {$booking->reference_number} was created.")
                    ->icon('heroicon-o-calendar-days')
                    ->color('success')
                    ->sendToDatabase($user);
            }
        }
    }
}

