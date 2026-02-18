<?php

namespace App\Observers;

use App\Events\AdminDashboardNotification;
use App\Events\BookingStatusUpdated;
use App\Models\Booking;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
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

        // Invalidate blocked-dates cache (booking affects availability)
        Cache::forget('api.blocked-dates');

        // Real-time: notify booking channel and admin dashboard
        BookingStatusUpdated::dispatch($booking);
        AdminDashboardNotification::dispatch('booking.created', 'New Booking', [
            'reference' => $booking->reference_number,
            'booking_id' => $booking->id,
        ]);
    }

    public function updated(Booking $booking): void
    {
        Cache::forget('api.blocked-dates');
        BookingStatusUpdated::dispatch($booking);
    }

    public function deleted(Booking $booking): void
    {
        Cache::forget('api.blocked-dates');
    }
}

