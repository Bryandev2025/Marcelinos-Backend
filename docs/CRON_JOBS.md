# Cron jobs, scheduler, and queues

This Laravel app relies on **one system cron entry** that runs the task scheduler every minute. Individual task timing is defined in `routes/console.php`. Background **queue workers** are separate: they process jobs (mail, notifications) that implement `ShouldQueue`.

---

## 1. Required server setup

### Task scheduler (required)

The OS must invoke Laravel’s scheduler **once per minute**:

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/project` with the directory that contains the `artisan` file.

**Hostinger (hPanel → Advanced → Cron Jobs, type “PHP”):** the panel prefixes `/usr/bin/php` and your home directory. In **Command to run**, enter only the path **from your home folder** to `artisan`, plus arguments, for example:

```text
domains/your-site.com/public_html/artisan schedule:run
```

Use **every minute** as the schedule. Adjust the path to match where `artisan` lives in File Manager.

### Queue worker (required if `QUEUE_CONNECTION` is not `sync`)

Production should use a real queue driver (this project defaults to **`database`** in `config/queue.php`). Then something must run `queue:work` (or equivalent).

On **shared hosting**, a long-lived `queue:work` process is often not ideal. A common pattern is a **second cron job every minute**:

```bash
* * * * * cd /path/to/project && php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

Hostinger PHP cron example (path is illustrative):

```text
domains/your-site.com/public_html/artisan queue:work --stop-when-empty --tries=3
```

Ensure migrations have created the `jobs` table (`php artisan migrate`) and `.env` has `QUEUE_CONNECTION=database` (or your chosen driver).

**Local development:** `composer run dev` already runs `queue:listen` alongside the dev server (see `composer.json`).

---

## 2. What `schedule:run` does

When cron runs `php artisan schedule:run`, Laravel checks **Asia/Manila** for each scheduled event (see `routes/console.php`) and runs commands that are due.

Verify registered tasks anytime:

```bash
php artisan schedule:list
```

Automated test: `tests/Feature/SchedulingTest.php` asserts the booking-related commands appear in `schedule:list`.

---

## 3. Scheduled tasks (application “cron jobs”)

All times below use the **`Asia/Manila`** timezone unless noted.

| Schedule (cron) | Artisan command | Purpose |
|----------------|-----------------|---------|
| `0,10,20,30,40,50 10 * * *` (10:00–10:50 Manila, every 10 minutes) | `bookings:complete-checkouts` | Sets **occupied** bookings to **completed** when `check_out` is due. |
| `0 11 * * *` (daily 11:00 Manila) | `bookings:complete-checkouts` | Same completion logic; extra run after the morning window. |
| *(after each successful `bookings:complete-checkouts` run above)* | `testimonials:send-feedback` | Sends testimonial feedback emails for **completed** stays where checkout was at least one day ago and email was not sent yet. **Not shown** as its own line in `schedule:list`; it is chained via `->after(...)`. |
| `0 12 * * *` (daily 12:00 Manila) | `bookings:activate-checkins` | For **today’s** check-in date, moves **paid** / **partial** bookings to **occupied**. |
| `0 12 * * *` (daily 12:00 Manila) | `bookings:send-reminders` | Sends reminder emails for guests whose **check-in is tomorrow** (one day before), if not already sent. |
| `*/15 * * * *` (every 15 minutes) | `bookings:cancel-unpaid` | Cancels **unpaid** bookings older than the grace period (default **3 days**, overridable with `--days`). |

Implementation details:

- **`bookings:complete-checkouts`** — `app/Console/Commands/CompleteCheckoutBookings.php`
- **`testimonials:send-feedback`** — `app/Console/Commands/SendTestimonialFeedback.php`
- **`bookings:activate-checkins`** — `app/Console/Commands/ActivateCheckinBookings.php`
- **`bookings:send-reminders`** — `app/Console/Commands/SendBookingReminders.php`
- **`bookings:cancel-unpaid`** — `app/Console/Commands/CancelPendingBookings.php`

Overlapping runs are mitigated with `withoutOverlapping()` on these scheduled commands where configured in `routes/console.php`.

---

## 4. Queue-backed mail and jobs (worker, not `schedule:run`)

These run when a **queue worker** processes the `jobs` table (or your configured backend). They are **not** started by `schedule:run` alone.

Examples in this codebase:

| Type | Class | Role |
|------|--------|------|
| Mailable | `App\Mail\BookingCreated` | Queued booking confirmation email |
| Mailable | `App\Mail\ContactReply` | Queued contact reply email |
| Notification | `App\Notifications\NewContactInquiry` | Queued contact inquiry notification |
| Job | `App\Jobs\SendBookingConfirmation` | Queued booking confirmation dispatch |
| Job | `App\Jobs\SendContactNotification` | Queued contact notification dispatch |

With `QUEUE_CONNECTION=sync`, jobs run immediately in the web request and **no** worker cron is needed (not recommended for production under load).

---

## 5. Manual checks

```bash
# List scheduler entries
php artisan schedule:list

# Run the scheduler once (as cron would)
php artisan schedule:run

# Dry-run a specific scheduled command
php artisan bookings:complete-checkouts
php artisan bookings:cancel-unpaid
php artisan testimonials:send-feedback
```

Use `--help` on any command for options (e.g. `bookings:cancel-unpaid --days=`).

---

## 6. Troubleshooting

| Symptom | What to check |
|---------|----------------|
| No booking status changes, no reminder emails | Cron not calling `schedule:run` every minute; server timezone vs `Asia/Manila` in definitions; `php artisan schedule:list` |
| Emails never send for queued mailables | `QUEUE_CONNECTION`, queue worker cron / process, `failed_jobs` table, logs |
| `testimonials:send-feedback` never runs | It only runs **after** a successful `bookings:complete-checkouts` scheduled run; verify checkout completion schedule and mail config |

---

## Related doc

`docs/cron-job.md` is an older, booking-focused scheduler overview. This file adds **Hostinger / queue worker** setup and the **full task table** in one place.
