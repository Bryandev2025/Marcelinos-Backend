# Notification System Workflow

This document explains the real-time notification architecture for Marcelino's Booking Backend. It outlines how database events (specifically Bookings) are securely pushed directly to active Staff and Admin users exactly as they happen, using highly active WebSockets.

## 1. Overview and Technology Stack

The notification system uses the following stack to accomplish real-time pushes:

- **Filament Admin Actions**: Handles UI generation for toasts, database persistence, and Notification Bells inside the backend dashboard.
- **Laravel Observers**: Listens for silent changes at the Database/Eloquent model level (e.g., watching `Booking`).
- **Pusher (websocket persistence)**: Handles the real-time active WebSocket delivery.
- **Laravel Echo**: The pre-built JavaScript client embedded in Filament used to listen over the Pusher protocol.

---

## 2. General Flow of a Real-Time Notification

The order of operations from a customer's click to the admin's screen:

1. **User Action:** A customer successfully books a room on the React frontend or cancels an itinerary.
2. **Database Change:** The API executes a database change inside the `bookings` table.
3. **Observer Intercept:** The `BookingObserver` hooks into the `created` or `updated` Eloquent event dynamically.
4. **Target Users:** The observer gathers `User` models that have `admin` or `staff` roles with an active account status.
5. **Database Notification Engine:** The system logs an internal persistent notification inside the database through Filament.
6. **Broadcaster / Pusher:** After persistence, the notification is immediately handed to the `->broadcast()` pipe. The Event payload queues and gets forwarded into Pusher.
7. **Filament Subscriptions:** Every logged-in Staff or Admin is already subscribed actively to a Private WebSocket channel named `App.Models.User.{id}`.
8. **UI Appearance:** Pusher routes the message exclusively to their private personal channel, rendering a clickable live toast pop-up on their screen without needing to refresh.

---

## 3. How the Code Operates

### A. The Trigger (Observer)

The trigger is isolated inside `app/Observers/BookingObserver.php`.

```php
// Creating a new booking
$users = User::whereIn('role', ['admin', 'staff'])->where('is_active', true)->get();

foreach ($users as $user) {
    Notification::make()
        ->title('New Booking Created')
        ->body("Booking {$booking->reference_number} was created.")
        ->icon('heroicon-o-calendar-days')
        ->color('success')
        ->actions([
            Action::make('view')
                ->label('View Booking')
                ->button()
                ->url(BookingResource::getUrl('view', ['record' => $booking]))
        ])
        ->sendToDatabase($user) // 1. Save locally so it appears in the Notification Bell history
        ->broadcast($user);    // 2. Fire to WebSockets for live push
}
```

### B. The Authorization (Channels)

So that guests cannot see Admin backend data, WebSockets require secured channels. This is handled in `routes/channels.php`.
Filament relies heavily on a user-scoped namespace scheme.

```php
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    // Only the exact logged-in staff member holding that ID can read this channel stream
    return (int) $user->id === (int) $id;
});
```

### C. Dashboard Receiver (Filament Provider)

`AdminPanelProvider.php` enforces that WebSockets are explicitly instructed to poll and listen inside the actual app framework via Native Filament directives:

```php
public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->databaseNotifications()
        ->databaseNotificationsPolling('30s') // Fallback polling
        ->broadcastNotifications();           // Actively listen to Echo/Pusher
}
```

---

## 4. Setting up for Testing / Production

To ensure that the notification push fires efficiently, three components need to interact in harmony as background processes.

To spin it up, execute these listeners:

1. **Serve the Main App:** `php artisan serve`
2. **Serve the Queues:** Broadcasted jobs inherently need an active queue worker since WebSocket push isn't instant in native PHP contexts. Run `php artisan queue:listen` or `php artisan queue:work`.
3. **Serve Pusher-compatible WebSockets:** ensure your Pusher endpoint (managed Pusher.com or a self-hosted compatible server) is reachable.

As soon as a booking's status switches to `'cancelled'` or a new record appears through an API trigger, the backend dashboard UI will immediately show an interactive Toast Notification linking deeply into the newly created asset view.
