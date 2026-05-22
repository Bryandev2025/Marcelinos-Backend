# Booking actor propagation and room checklist autogen

Summary
-------

This document explains two recent changes made to the backend to fix audit attribution and to stop unnecessary room checklist auto-creation:

- Introduced `BookingActorContext` to explicitly propagate the initiating user (actor) across nested model writes so activity logs reflect the correct admin/staff user.
- Added an environment toggle `ROOM_CHECKLIST_AUTOGEN` to disable automatic creation of `RoomChecklist` / `RoomChecklistItem` rows when your room model or other systems already provide the checklist data.

Why
---

1. Relying on `auth()->user()` at model event time is brittle: nested writes (for example, a `Payment` model `booted()` handler that recomputes booking status) can happen without the original web request auth context, producing incorrect `triggered_by_user` entries in activity logs.
2. The `ensureCompletionRoomChecklists()` helper auto-generated checklist items per-room on booking completion/checkout. A global listener that logs `eloquent.created: *` produced noisy audit entries for those system-generated rows. If rooms already include checklist data, auto-generation is unnecessary.

Files touched
------------

- [app/Support/BookingActorContext.php](app/Support/BookingActorContext.php) — new helper to run code with an explicit actor and to read the current actor.
- [app/Observers/BookingObserver.php](app/Observers/BookingObserver.php) — now prefers the `BookingActorContext::current()` actor when logging activity.
- [app/Support/BookingLifecycleActions.php](app/Support/BookingLifecycleActions.php) — wrapped lifecycle mutations with `BookingActorContext::run(...)`; added `ROOM_CHECKLIST_AUTOGEN` guard to `ensureCompletionRoomChecklists()`.
- Several Filament admin files and payment/queue listeners were updated to call `BookingActorContext::run(...)` when they mutate bookings.

How to disable automatic checklist creation
----------------------------------------

Set the following environment variable in your environment or `.env` file:

```
ROOM_CHECKLIST_AUTOGEN=false
```

When false, `BookingLifecycleActions::ensureCompletionRoomChecklists()` becomes a no-op and will not create `RoomChecklist` or `RoomChecklistItem` rows.

How to use `BookingActorContext`
--------------------------------

There are two helper methods:

- `BookingActorContext::run(?User $actor, Closure $fn): mixed` — runs `$fn` while establishing `$actor` as the current actor.
- `BookingActorContext::current(): ?User` — returns the current actor, if set.

Example (already applied in codebase):

```
BookingActorContext::run(auth()->user(), function () use ($booking) {
    $booking->update(['booking_status' => Booking::BOOKING_STATUS_COMPLETED]);
});
```

Notes and next steps
--------------------

- If you prefer to keep auto-generation but suppress activity logs for system-generated checklist items, we can instead mark those creations as system (e.g. using a `generated_by_system` flag) and update the global activity listener to ignore them.
- If you want, I can add a `.env.example` entry or set `ROOM_CHECKLIST_AUTOGEN=false` for you locally and run a quick scenario to confirm noisy logs stop.

Contact
-------

If you want changes to the behavior (toggle vs. suppression), tell me which approach you prefer and I will implement it and update this doc accordingly.
