# Booking Date Filter (Admin)

## Purpose (plain words)
Let staff quickly see bookings for a time period (today, this week, last year, etc.) or pick any custom dates.

## Where it appears
Bookings table in the admin panel.

## How to use it (step‑by‑step)
1. Open **Filter by Dates**.
2. **Quick dates**: pick a ready‑made range like **Today**, **Next 7 days**, **Last year**, etc.
3. **Custom dates**: turn **Use custom dates** ON to show **From** and **To**, then choose any range.

## What each quick option means
- **Today**: only bookings that touch today.
- **Next 7 days**: bookings that touch the coming 7 days (including today).
- **Next 30 days**: bookings that touch the coming 30 days (including today).
- **This month**: bookings that touch any day in the current month.
- **Last month**: bookings that touch any day in the previous month.
- **Last 30 days**: bookings that touch the past 30 days.
- **Last year**: bookings that touch any day in last year.
- **Last 2 years**: bookings that touch any day from the last 2 full years.
- **This year**: bookings that touch any day this year.

## What “overlap” means (simple)
If a booking’s stay touches your range, it will show.

Example range: Feb 4–Feb 10
- Booking Feb 1–Feb 5 ✅ shows (it overlaps Feb 4–5)
- Booking Feb 10–Feb 12 ✅ shows (it overlaps Feb 10)
- Booking Feb 11–Feb 12 ❌ does not show

## How it works (technical)
- The filter uses the overlap rule:
  - `check_in` < End date **AND** `check_out` > Start date
- If only **From** or **To** is provided, it still filters correctly.

## File location
- [app/Filament/Resources/Bookings/Tables/BookingsTable.php](app/Filament/Resources/Bookings/Tables/BookingsTable.php)
