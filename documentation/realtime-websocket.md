# Real-Time WebSocket (Laravel Reverb + Laravel Echo)

This document describes the real-time architecture, how to use it, and senior-level practices for scaling and maintaining it.

---

## 1. Architecture Overview

```
┌─────────────────┐     HTTP/WS      ┌──────────────────┐     WebSocket      ┌─────────────────┐
│  React Client   │ ◄──────────────► │  Laravel Reverb  │ ◄────────────────► │  Browser Tab     │
│  (Laravel Echo) │   /broadcasting  │  (WS Server)     │   Pusher protocol  │  (same client)   │
└────────┬────────┘     /auth        └────────┬─────────┘                    └─────────────────┘
         │                                    │
         │ REST API                            │ receives events from
         ▼                                    ▼
┌─────────────────┐                 ┌──────────────────┐
│  Laravel API    │ ── broadcast ──► │  Queue worker     │
│  (events)       │   (queue)        │  (php artisan     │
└─────────────────┘                  │   queue:work)     │
                                     └──────────────────┘
```

- **Laravel** dispatches broadcast events (e.g. `BookingStatusUpdated`). Events are queued and sent to **Reverb**.
- **Reverb** holds WebSocket connections and pushes events to subscribed clients using the Pusher protocol.
- **Laravel Echo** (in the React app) connects to Reverb, subscribes to channels, and listens for events.

**Conventions used in this project:**

- **Channel names** are centralized in `App\Broadcasting\BroadcastChannelNames` (backend) and `src/lib/realtime/channels.ts` (frontend).
- **Event names** are the class short name (e.g. `BookingStatusUpdated`). Listen with `.listen('.BookingStatusUpdated', callback)`.
- **Broadcast events** extend `App\Events\BaseBroadcastEvent` and implement `broadcastOn()` and `broadcastWith()`.

---

## 2. Backend: Configuration

### 2.1 Environment (.env)

**Required for real-time sync:** `BROADCAST_CONNECTION` must be `reverb`. If it is `log`, events are only written to the log and the React client never receives them.

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=marcelinos-app
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=*
```

- **Development:** Use `REVERB_ALLOWED_ORIGINS=*` or comma-separated origins (e.g. `http://localhost:5173`).
- **Production:** Set `REVERB_SCHEME=https`, a proper host, and restrict `REVERB_ALLOWED_ORIGINS`.

### 2.2 Running Reverb and queue worker

For the React client to receive updates **without a page reload**, two processes must be running:

| Process | Command | Purpose |
|--------|---------|--------|
| **Reverb** | `php artisan reverb:start` | WebSocket server; delivers events to the browser |
| **Queue worker** | `php artisan queue:work` | Runs queued jobs; broadcast events are queued, so this sends them to Reverb |

- **Manual:** Run both in separate terminals (or use `composer run dev` if it starts server, Reverb, and queue).
- If the queue worker is not running, events are never broadcast and the frontend will not refetch until the user reloads.

---

## 3. Backend: Adding a New Broadcast Event

### 3.1 Create the event

1. Create a class in `app/Events/` that extends `App\Events\BaseBroadcastEvent`.
2. Implement `broadcastOn()` (channel(s)) and `broadcastWith()` (payload).
3. Use `App\Broadcasting\BroadcastChannelNames` for channel names so they stay in sync with the frontend.

**Example:**

```php
<?php

namespace App\Events;

use App\Broadcasting\BroadcastChannelNames;
use App\Models\ContactUs;
use Illuminate\Broadcasting\PrivateChannel;

final class ContactFormSubmitted extends BaseBroadcastEvent
{
    public function __construct(
        public ContactUs $contact
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(BroadcastChannelNames::adminDashboard()),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->contact->id,
            'subject' => $this->contact->subject,
            'created_at' => $this->contact->created_at->toIso8601String(),
        ];
    }
}
```

### 3.2 Dispatch the event

Dispatch from a controller, observer, or job:

```php
use App\Events\ContactFormSubmitted;

ContactFormSubmitted::dispatch($contact);
```

### 3.3 Channel authorization (private channels)

Edit `routes/channels.php` to define who can subscribe. Example for a new private channel:

```php
Broadcast::channel('admin.dashboard', function ($user) {
    return in_array($user->role ?? null, ['admin', 'staff'], true);
});
```

Channel names in `channels.php` are **without** the `private-` prefix; Laravel adds it for private channels.

---

## 4. Backend: Adding a New Channel

1. **Add the channel name** in `App\Broadcasting\BroadcastChannelNames`:

   ```php
   public static function contactAlerts(): string
   {
       return 'admin.contact-alerts';
   }
   ```

2. **Authorize it** in `routes/channels.php`:

   ```php
   Broadcast::channel('admin.contact-alerts', function ($user) {
       return in_array($user->role ?? null, ['admin', 'staff'], true);
   });
   ```

3. **Use it in events** via `BroadcastChannelNames::contactAlerts()`.

---

## 5. Frontend: Configuration

### 5.1 Environment (.env)

Optional; defaults are derived from the API URL and `local-key`:

```env
# Optional overrides
VITE_WS_HOST=localhost
VITE_WS_PORT=8080
VITE_WS_KEY=local-key
VITE_WS_SCHEME=http
```

### 5.2 Authentication (private channels)

Private channels require the Laravel app to authorize the user. The Echo client sends a request to `/broadcasting/auth` with the channel name. For API-based auth (e.g. Sanctum):

- The client must send `Authorization: Bearer <token>` with that request.
- By default, the Echo client reads the token from `localStorage.getItem('token')`.
- To use a different source (e.g. auth context), call `setEchoTokenGetter(() => yourAuth.getToken())` before any subscription.

**After login:** call `disconnectEcho()` so the next subscription uses a fresh token (Echo is recreated on next `getEcho()`).

---

## 6. Frontend: Using Realtime in React

### 6.1 Listen for one event (recommended)

Use `useRealtimeEvent` with a channel and event name. Payload types are defined in `src/types/realtime.types.ts`.

```tsx
import { useQueryClient } from "@tanstack/react-query";
import { useRealtimeEvent } from "@/hooks/useRealtimeEvent";
import { RealtimeChannels } from "@/lib/realtime/channels";
import { queryKeys } from "@/lib/api/endpoints";

function BookingReceiptPage({ referenceNumber }: { referenceNumber: string }) {
  const queryClient = useQueryClient();

  useRealtimeEvent({
    channel: RealtimeChannels.booking(referenceNumber),
    event: "BookingStatusUpdated",
    enabled: !!referenceNumber,
    onEvent: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.bookings.byReference(referenceNumber) });
      queryClient.invalidateQueries({ queryKey: ["booking-receipt", referenceNumber] });
    },
  });

  // ... rest of component
}
```

**Note:** Private channels require an authenticated user. For guest-only pages (e.g. public receipt), either use a public channel for that event or skip realtime and rely on polling/refresh.

### 6.2 Subscribe to a channel and bind multiple events

Use `useRealtimeChannel` to subscribe, then bind listeners (e.g. with `getEcho().private(channel).listen(...)` inside the same effect or in a child). Prefer `useRealtimeEvent` when you only need one event.

### 6.3 Adding a new event type on the frontend

1. **Extend payload types** in `src/types/realtime.types.ts`:

   ```ts
   export interface MyNewEventPayload {
     id: number;
     name: string;
   }

   export interface RealtimeEventMap {
     // ... existing
     MyNewEvent: MyNewEventPayload;
   }
   ```

2. Use `useRealtimeEvent` with `event: "MyNewEvent"` and `onEvent` will be typed.

### 6.4 Global subscriber: sync without page reload

The app uses **`useRealtimeGlobalSubscriber`** (mounted in `App.tsx`) to listen to resource-update events (rooms, venues, gallery, reviews, blocked-dates) and keep the React UI in sync with the backend **without a page reload**.

- When the backend broadcasts (e.g. `RoomsUpdated`), the subscriber runs `queryClient.invalidateQueries({ queryKey })` and `queryClient.refetchQueries({ queryKey })`.
- Use the **shared “all” query key** (e.g. `queryKeys.rooms.all` = `["rooms"]`) so that all related queries are matched: TanStack Query uses prefix matching, so `["rooms"]` matches `["rooms", "home"]` and `["rooms", checkIn, checkOut]`.
- Any component that fetches data with a query key starting with that prefix will refetch when the event is received, and the UI updates automatically.

To add a new resource to this flow, see [§11 How to add a new synchronization](#11-how-to-add-a-new-synchronization).

---

## 7. Senior Dev Practices

### 7.1 Single source of truth for channel names

- **Backend:** `App\Broadcasting\BroadcastChannelNames`.
- **Frontend:** `src/lib/realtime/channels.ts`.

When adding a channel, update both and keep naming consistent (e.g. `booking.{reference}`, `admin.dashboard`).

### 7.2 Keep payloads small and stable

- Only include data the client needs to update UI or invalidate cache.
- Prefer IDs and timestamps; avoid large nested objects.
- Avoid renaming or removing keys; add new keys optionally for backward compatibility.

### 7.3 Queue all broadcast events

`BaseBroadcastEvent` uses Laravel’s `ShouldBroadcast`, so events are queued by default. This keeps request latency low and allows horizontal scaling of workers. Do not broadcast synchronously in hot paths.

### 7.4 Scaling Reverb

- **Single server:** Running `php artisan reverb:start` on the app server is fine for moderate traffic.
- **Multiple servers:** Use a Redis adapter so Reverb instances share state. See [Laravel Reverb docs](https://laravel.com/docs/reverb) for Redis configuration.
- **High availability:** Put Reverb behind a load balancer with sticky sessions (or use Redis adapter and scale Reverb horizontally).

### 7.5 Security

- **Private channels:** Always authorize in `routes/channels.php`. Do not return `true` for channels that should be restricted.
- **CORS:** In production, set `REVERB_ALLOWED_ORIGINS` to your frontend origin(s) only.
- **Secrets:** Keep `REVERB_APP_SECRET` and app keys out of the frontend; only the key (e.g. `REVERB_APP_KEY`) is public for the client.

### 7.6 Testing

- **Backend:** Dispatch the event and assert it is broadcast (e.g. `Event::fake()` and assert the event was pushed to the queue or use Reverb testing utilities if available).
- **Frontend:** Mock `getEcho()` and assert that the component invalidates queries or updates state when the mock triggers the event.

### 7.7 Disabling realtime

- **Backend:** Set `BROADCAST_CONNECTION=log` or `null` in `.env`. Events will be logged or dropped; no Reverb needed.
- **Frontend:** If `VITE_WS_KEY` (or host) is unset, `getEcho()` returns `null` and hooks no-op. No need to change component code.

---

## 8. File Reference

| Layer   | File / location |
|--------|-------------------|
| Backend | `config/broadcasting.php`, `config/reverb.php` |
| Backend | `routes/channels.php` – channel authorization |
| Backend | `app/Providers/BroadcastServiceProvider.php` – auth route + load channels |
| Backend | `app/Broadcasting/BroadcastChannelNames.php` – channel name constants |
| Backend | `app/Events/BaseBroadcastEvent.php` – base broadcast event |
| Backend | `app/Events/BookingStatusUpdated.php`, `BlockedDatesUpdated.php`, `RoomsUpdated.php`, `VenuesUpdated.php`, `GalleryUpdated.php`, `ReviewsUpdated.php`, `AdminDashboardNotification.php` |
| Frontend | `src/lib/realtime/echo.ts` – Echo singleton |
| Frontend | `src/lib/realtime/channels.ts` – channel name helpers |
| Frontend | `src/types/realtime.types.ts` – event payload types |
| Frontend | `src/hooks/useRealtimeEvent.ts`, `useRealtimeChannel.ts` – React hooks |
| Frontend | `src/hooks/useRealtimeGlobalSubscriber.ts` – global listener for rooms, venues, gallery, reviews, blocked-dates; invalidates/refetches so UI syncs without reload |
| Frontend | `src/App.tsx` – mounts `useRealtimeGlobalSubscriber()` so realtime is active app-wide |

---

## 9. Troubleshooting

| Issue | Check |
|-------|--------|
| No events received / UI doesn’t update without reload | **`BROADCAST_CONNECTION=reverb`** (not `log`). Reverb running? **Queue worker running** (`php artisan queue:work`)? Frontend has `VITE_WS_HOST`, `VITE_WS_KEY` (and optionally `VITE_WS_PORT`, `VITE_WS_SCHEME`) so `getEcho()` is not null. |
| 403 on private channel | User authenticated? Token sent to `/broadcasting/auth`? Channel callback in `channels.php` returns true for that user? |
| CORS errors | `REVERB_ALLOWED_ORIGINS` includes the frontend origin. |
| Wrong payload in React | Ensure `broadcastWith()` and `RealtimeEventMap` in `realtime.types.ts` match. |

---

## 10. Frontend realtime usage (API + React only)

WebSocket is used only for the **React frontend**; the Filament admin uses **polling** (e.g. Bookings table every 10s).

| Data | Event | Channel | Where it’s used |
|------|--------|---------|------------------|
| Blocked dates | `BlockedDatesUpdated` | `blocked-dates` | `FormWrapper` (calendar/booking form) |
| Booking status | `BookingStatusUpdated` | `booking.{reference}` | `BookingReceiptPage` |
| Rooms | `RoomsUpdated` | `rooms` | `Step1` (booking), `RoomCard` (homepage) |
| Venues | `VenuesUpdated` | `venues` | `Step1` (booking), `EventVenues` (homepage) |
| Gallery | `GalleryUpdated` | `gallery` | `OurGallery` (homepage) |
| Reviews / testimonials | `ReviewsUpdated` | `reviews` | `ReviewSection` / `ClientReviews` (landing) |

All of the above use **public** channels (`isPrivate: false`), so no auth is required.

---

## 11. How to add a new synchronization

Use this checklist when you want a new resource (e.g. “promos” or “amenities”) to stay in sync between backend (e.g. Filament) and the React client **without a page reload**.

### 11.1 Backend

1. **Channel name (if new)**  
   In `App\Broadcasting\BroadcastChannelNames`, add a method that returns the channel string (e.g. `promos` for a public channel). Use this in your event’s `broadcastOn()`.

2. **Event class**  
   In `app/Events/`, create a class that extends `App\Events\BaseBroadcastEvent`:
   - `broadcastOn()`: return `[new Channel(BroadcastChannelNames::yourChannel())]` for a public channel.
   - `broadcastWith()`: return a small payload (e.g. `['updated_at' => now()->toIso8601String()]`).

3. **Dispatch the event**  
   Dispatch it whenever the resource changes:
   - **Model changes:** Create an Observer for the model (e.g. `app/Observers/PromoObserver.php`) and in `saved()` and `deleted()` call `YourEvent::dispatch()`. Register the observer in `App\Providers\AppServiceProvider` with `YourModel::observe(YourObserver::class)`.
   - **One-off actions:** Dispatch from a controller or Filament action after create/update/delete.

4. **Config**  
   Ensure `BROADCAST_CONNECTION=reverb` in `.env`, and that **Reverb** and **queue worker** are running (see §2.2).

### 11.2 Frontend

1. **Channel name**  
   In `src/lib/realtime/channels.ts`, add a function that returns the same channel name as the backend (e.g. `promos: () => "promos"`).

2. **Query keys**  
   In `src/lib/api/endpoints.ts`, add a query key factory for the resource, including an `all` key used for invalidation (e.g. `promos: { all: ["promos"] as const, ... }`). Use these keys in any `useApiQuery` / `useQuery` that fetches this resource so they share the same prefix (e.g. `["promos"]`, `["promos", "home"]`).

3. **Subscribe and refetch**  
   In `src/hooks/useRealtimeGlobalSubscriber.ts`:
   - Add an entry to the `channels` array: `{ channel: RealtimeChannels.promos(), event: ".PromosUpdated", queryKey: queryKeys.promos.all }`.
   - The event name must match the backend class short name (e.g. `PromosUpdated` → listen for `".PromosUpdated"`).

4. **Use the query keys in the UI**  
   Wherever you fetch the resource (e.g. homepage, booking step), use a query key that **starts with** the same segment as `queryKeys.promos.all` (e.g. `["promos"]` or `["promos", "home"]`). When the event is received, `invalidateQueries` and `refetchQueries` will run for all matching queries and the UI will update without a reload.

### 11.3 Summary checklist

| Step | Backend | Frontend |
|------|--------|----------|
| 1 | Add channel in `BroadcastChannelNames` | Add same channel in `channels.ts` |
| 2 | Create event class extending `BaseBroadcastEvent` | — |
| 3 | Dispatch event (Observer, controller, or Filament) | — |
| 4 | — | Add `queryKeys.*.all` in `endpoints.ts`; use in queries |
| 5 | — | Add channel + event + queryKey in `useRealtimeGlobalSubscriber` |
| 6 | Run Reverb + queue worker | Ensure WS env vars (e.g. `VITE_WS_*`) so Echo connects |

If the frontend still does not update, verify: `BROADCAST_CONNECTION=reverb`, queue worker running, Reverb running, and frontend env (e.g. `VITE_WS_KEY`, `VITE_WS_HOST`) set so `getEcho()` is not null.
