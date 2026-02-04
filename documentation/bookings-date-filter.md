# Booking Date Filter (Admin)

## Purpose
Provide an easy, booking‑system style date filter for the Bookings table. It supports common date types (stay dates, arrival, departure, and booking creation) with quick presets and custom ranges.

## Where it appears
Bookings table in the admin panel.

## What users see
**Filter by Dates**
- **Date type**
  - **Stay dates (any overlap)**: Shows bookings whose stay overlaps the selected range.
  - **Arrival date (check‑in)**: Shows bookings where `check_in` falls in the range.
  - **Departure date (check‑out)**: Shows bookings where `check_out` falls in the range.
  - **Booking created date**: Shows bookings where `created_at` falls in the range.
- **Quick dates** (optional)
  - Today, Next 7 days, Next 30 days, This month, Last month, Last 30 days, This year
- **From / To** (custom range)

## How it works
- **Stay dates (any overlap)** uses the overlap rule:
  - `check_in` < End date **AND** `check_out` > Start date
- **Arrival date** filters by `check_in`.
- **Departure date** filters by `check_out`.
- **Booking created date** filters by `created_at`.
- If only one side of the range is provided, the filter still works (e.g., all bookings after a start date).

## File location
- [app/Filament/Resources/Bookings/Tables/BookingsTable.php](app/Filament/Resources/Bookings/Tables/BookingsTable.php)
