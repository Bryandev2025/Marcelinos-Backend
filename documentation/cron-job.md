# Laravel Booking Scheduler

This document describes the scheduled tasks configured in `routes/console.php` using **Laravel Scheduler**.

These tasks automatically update booking statuses based on the **current date** and **booking state**. They use **Artisan commands** and the **Booking model**.

**Laravel reference:** [Task Scheduling](https://laravel.com/docs/11.x/scheduling#scheduling-artisan-commands)

---

## Timezone

All scheduled times use **Asia/Manila (UTC+8)**.

- Application default: `config('app.timezone')` is `Asia/Manila` (overridable via `APP_TIMEZONE` in `.env`).
- Each scheduled task is explicitly set to `->timezone('Asia/Manila')`.

---

## Overview

The application uses **Laravel's task scheduling**. A single system cron job runs every minute and triggers `php artisan schedule:run`; Laravel then runs the defined tasks at the correct times.

**Definition:** `routes/console.php`

**Commands:** `app/Console/Commands/`

- `CompleteCheckoutBookings.php` → `bookings:complete-checkouts`
- `ActivateCheckinBookings.php` → `bookings:activate-checkins`
- `CancelPendingBookings.php` → `bookings:cancel-pending`

---

## Scheduled Tasks

### 1. Complete checked-out bookings

| Item    | Value                    |
|--------|---------------------------|
| Command| `bookings:complete-checkouts` |
| Schedule | Daily at **10:00** (Asia/Manila) |

**Logic:**

- Date used: today (Asia/Manila), or `--date=Y-m-d` when run manually.
- Selects bookings where:
  - `check_out` date = that date  
  - `status` = `occupied`
- Sets `status` to `complete`.

**Purpose:** Mark stays as completed after check-out and free the related rooms (via the Booking model’s `saved` event).

---

### 2. Activate check-in bookings (paid → occupied)

| Item    | Value                    |
|--------|---------------------------|
| Command| `bookings:activate-checkins` |
| Schedule | Daily at **12:00** (Asia/Manila) |

**Logic:**

- Date used: today (Asia/Manila), or `--date=Y-m-d` when run manually.
- Selects bookings where:
  - `check_in` date = that date  
  - `status` = `paid`
- Sets `status` to `occupied`.

**Purpose:** Mark paid bookings as occupied on check-in day.

---

### 3. Cancel pending bookings (no-show)

| Item    | Value                    |
|--------|---------------------------|
| Command| `bookings:cancel-pending` |
| Schedule | Daily at **12:00** (Asia/Manila) |

**Logic:**

- Date used: today (Asia/Manila), or `--date=Y-m-d` when run manually.
- Selects bookings where:
  - `check_in` date = that date  
  - `status` = `pending`
- Sets `status` to `cancelled`.

**Purpose:** Cancel bookings that were not paid by check-in date.

---

## Server setup (cron)

Only **one** cron entry is needed. Laravel runs all scheduled tasks from it.

### Crontab (Linux / production)

```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/your/project` with the application root (e.g. `/var/www/be-marcelinos`).

### Windows (Task Scheduler)

Run every minute:

```text
php artisan schedule:run
```

Use the full path to `php.exe` and set the working directory to the project root.

---

## Testing and debugging

### List scheduled tasks

```bash
php artisan schedule:list
```

Shows each task and its next run time (in Asia/Manila).

### Run the scheduler once (all due tasks)

```bash
php artisan schedule:run
```

### Run a single command (manual)

Useful for testing or backfills.

```bash
# Use “today” (Asia/Manila)
php artisan bookings:complete-checkouts
php artisan bookings:activate-checkins
php artisan bookings:cancel-pending

# Use a specific date (Y-m-d)
php artisan bookings:complete-checkouts --date=2025-02-09
php artisan bookings:activate-checkins --date=2025-02-09
php artisan bookings:cancel-pending --date=2025-02-09
```

### List booking-related commands

```bash
php artisan list bookings
```

---

## Optional: timezone in .env

Default is **Asia/Manila**. To make it explicit or override per environment, add to `.env`:

```env
APP_TIMEZONE=Asia/Manila
```

Example override (e.g. for tests):

```env
APP_TIMEZONE=UTC
```
