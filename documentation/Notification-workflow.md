# Booking Notifications Documentation

## Overview

The booking notification system is designed to alert admin and staff users in the Filament admin panel whenever a new booking is created. Notifications are sent via Filament's database notification system, which stores them in the `notifications` table and displays them in the top-right notification bell of the admin panel.

## Components

### 1. Booking Observer (`app/Observers/BookingObserver.php`)

The `BookingObserver` listens for the `created` event on the `Booking` model. When a new booking is created, it:

- Retrieves all active users with roles 'admin' or 'staff'.
- Sends a database notification to each user using Filament's `Notification::make()->sendToDatabase()`.

#### Code Structure

```php
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