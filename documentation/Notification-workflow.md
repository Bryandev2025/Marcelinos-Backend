# Booking Notifications Documentation

## Overview

The booking notification system is designed to alert admin users in the Filament admin panel whenever a new booking is created. Notifications are sent via Filament's database notification system, which stores them in the `notifications` table and displays them in the top-right notification bell of the admin panel.

This system was implemented and debugged over multiple iterations to resolve issues with notifications not saving, not displaying, and duplicating.

## Run
```
php artisan queue:work
```

## Components

### 1. Booking Observer (`app/Observers/BookingObserver.php`)

The `BookingObserver` listens for the `created` event on the `Booking` model. When a new booking is created, it:

- Retrieves all active users with roles 'admin' or 'staff'.
- Sends a database notification to each user using the custom `BookingCreatedNotification` class.

#### Code Structure

```php
<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Booking;
use App\Notifications\BookingCreatedNotification;
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
                $user->notify(new BookingCreatedNotification($booking));
            }
        }
    }
}
```

### 2. BookingCreatedNotification (`app/Notifications/BookingCreatedNotification.php`)

This custom notification class defines the structure of the notification, including title, body, icon, and color. It extends Laravel's `Notification` class and implements the `DatabaseNotification` interface for Filament compatibility.

#### Code Structure

```php
<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class BookingCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return FilamentNotification::make()
            ->title('New Booking Created')
            ->body("Booking {$this->booking->reference_number} was created.")
            ->icon('heroicon-o-calendar-days')
            ->color('success')
            ->getDatabaseMessage();
    }
}
```

### 3. Booking Model (`app/Models/Booking.php`)

The `Booking` model includes the observer registration to trigger notifications on creation.

#### Relevant Code Snippet

```php
<?php

namespace App\Models;

use App\Observers\BookingObserver;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected static function booted()
    {
        static::observe(BookingObserver::class);
    }

    // ... other model code
}
```

### 4. User Model (`app/Models/User.php`)

The `User` model implements the `Notifiable` trait to enable notifications.

#### Relevant Code Snippet

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    // ... other model code
}
```