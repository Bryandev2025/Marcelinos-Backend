# Google Sheets Booking Status Sync

This document explains how booking records are synced to Google Sheets by status tab and how the integration was implemented.

## Goal

Keep one Google Spreadsheet in sync with booking status changes from the backend and Filament:

- `Unpaid`
- `Partial`
- `Paid`
- `Complete`
- `Checked in`
- `Cancel`
- `Rescheduled` (optional, because this status exists in the model)

When booking status changes, the row is removed from the old status tab and moved to the new one.

## What We Implemented

### 1) Installed Google API client

Dependency added in `composer.json`:

- `google/apiclient`

### 2) Added Google Sheets config

Configured in `config/services.php` under `google_sheets`:

- `enabled`
- `spreadsheet_id`
- `credentials_path`
- `status_to_sheet` mapping

### 3) Added environment keys

Documented in `.env.example`:

- `GOOGLE_SHEETS_ENABLED`
- `GOOGLE_SHEETS_SPREADSHEET_ID`
- `GOOGLE_SHEETS_CREDENTIALS_PATH`
- tab names per status

Project `.env` must include real values.

### 4) Added sync service

Created `app/Services/GoogleSheetsBookingSyncService.php`.

Responsibilities:

- Authenticate using service account JSON.
- Ensure required status tabs exist in spreadsheet.
- Build row data from booking + guest + payment details.
- Remove booking row from all status tabs.
- Insert booking row into the target status tab.
- Remove row from all tabs when booking is deleted.

### 5) Added queued job

Created `app/Jobs/SyncBookingToGoogleSheet.php`.

Responsibilities:

- Fetch booking by ID (including soft deleted).
- Call sync service for create/update events.
- Call remove-only flow for delete events.

### 6) Hooked sync into booking lifecycle

Updated `app/Observers/BookingObserver.php` to dispatch sync job on:

- `created`
- `updated`
- `deleted`

This ensures Filament status updates are reflected in Google Sheets automatically.

### 7) Added full mirror sync command

Created `bookings:sync-google-sheet` to rebuild all spreadsheet tabs from the database.

Responsibilities:

- Re-read every booking row from DB.
- Rebuild each status tab from scratch (header + DB rows only).
- Remove any manual/extra rows that are not in DB.
- Guarantee spreadsheet content mirrors the database snapshot.

This command is also scheduled hourly in `routes/console.php`.

## Status Mapping

Internal booking status to sheet/tab label:

- `unpaid` -> `Unpaid`
- `partial` -> `Partial`
- `paid` -> `Paid`
- `completed` -> `Complete`
- `occupied` -> `Checked in`
- `cancelled` -> `Cancel`
- `rescheduled` -> `Rescheduled`

## Setup Checklist

1. Create one Google Spreadsheet (any name).
2. Set in `.env`:
   - `GOOGLE_SHEETS_ENABLED=true`
   - `GOOGLE_SHEETS_SPREADSHEET_ID=<spreadsheet-id>`
   - `GOOGLE_SHEETS_CREDENTIALS_PATH=storage/app/google-credentials.json`
3. Put service account JSON file in `storage/app/google-credentials.json`.
4. Share spreadsheet with service account `client_email` as **Editor**.
5. Run:
   - `php artisan config:clear`
   - `php artisan queue:work`
   - `php artisan bookings:sync-google-sheet` (initial full backfill)

## How To Get Spreadsheet ID

From URL:

`https://docs.google.com/spreadsheets/d/<THIS_IS_THE_ID>/edit#gid=0`

Use `<THIS_IS_THE_ID>` as `GOOGLE_SHEETS_SPREADSHEET_ID`.

## Data Written Per Row

Each status tab stores:

- Reference Number
- Status
- Guest Name
- Guest Email
- Guest Contact
- Check In
- Check Out
- Rooms
- Venues
- Total Price
- Amount Paid
- Balance
- Payment Method
- Updated At

## Expected Runtime Behavior

- New booking starts in matching status tab.
- Status changes move row between tabs.
- Edits update latest row data.
- Delete removes booking row from all tabs.
- Missing tabs are auto-created by the service.

## Troubleshooting

### No rows appear

- Confirm `GOOGLE_SHEETS_ENABLED=true`.
- Confirm queue worker is running (`php artisan queue:work`).
- Confirm spreadsheet ID is correct.
- Confirm credentials path is correct.

### Permission errors

- Spreadsheet must be shared with service account email from `google-credentials.json` (`client_email`) as **Editor**.

### Changes delayed

- Queue worker not running or backed up.
- Start/restart worker and retry booking update.
