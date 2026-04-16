<?php

use App\Models\Booking;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channel Authorization
|--------------------------------------------------------------------------
|
| Here you may register event broadcasting channel authorization callbacks.
| These callbacks determine whether the current user can listen on the
| channel. Use Channel::name() for public channels (no auth).
|
| Convention: use a consistent prefix per domain, e.g. "bookings", "admin".
|
*/

// Public channel: no authorization (anyone can subscribe)
// Broadcast::channel('bookings', fn () => true);

// Private channel: only authenticated users matching the booking can listen
Broadcast::channel(
    'booking.{reference}',
    function ($user, string $reference) {
        if (in_array($user->role ?? null, ['admin', 'staff'], true)) {
            return true;
        }

        $booking = Booking::query()
            ->with('guest:id,email')
            ->where('reference_number', $reference)
            ->first();

        if (! $booking || ! $booking->guest) {
            return false;
        }

        return strcasecmp((string) $booking->guest->email, (string) ($user->email ?? '')) === 0;
    }
);

// Admin/Staff dashboard channel (private)
Broadcast::channel(
    'admin.dashboard',
    function ($user) {
        return in_array($user->role ?? null, ['admin', 'staff'], true);
    }
);

Broadcast::channel('booking.{reference}.cancelled', function ($user, string $reference) { 
    if (in_array($user->role ?? null, ['admin', 'staff'], true)) {
        return true;
    }

    $booking = Booking::query()
        ->with('guest:id,email')
        ->where('reference_number', $reference)
        ->first();

    if (! $booking || ! $booking->guest) {
        return false;
    }

    return strcasecmp((string) $booking->guest->email, (string) ($user->email ?? '')) === 0;
});
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
