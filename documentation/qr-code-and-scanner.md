# QR Code Generation & Scanner (Bookings)

## Overview
This document explains how QR codes are generated for bookings and how the QR scanner works in the Filament admin panel. The QR payload includes the booking identifier and reference number to allow reliable lookup.

## Dependencies
The QR feature relies on:
- `simplesoftwareio/simple-qrcode` for QR code generation.
- `jeffersongoncalves/filament-qrcode-field` for QR scanning input in Filament forms.

See Composer requirements in `composer.json`.

## QR Code Generation
**Trigger:** A booking QR code is created automatically *after* the booking record is created.

**Location:** `app/Models/Booking.php`

**Flow:**
1. A `reference_number` is generated on `creating`.
2. On `created`, a JSON payload is built:
   ```json
   {
     "booking_id": <id>,
     "reference": "MWA-YYYY-XXXXXX",
     "guest_id": <id>
   }
   ```
3. The QR image is generated as SVG (`size(300)`) and stored in `storage/app/public/qr/bookings/`.
4. The booking record is updated silently with `qr_code` path to avoid event loops.

**Implementation details:**
- Uses `QrCode::size(300)->generate($qrData)` for SVG content.
- Stored with `Storage::disk('public')->put($path, ...)`.
- Path uses a UUID to avoid collisions.

## QR Code Display (Admin UI)
QR codes are surfaced in two places:

### Bookings Table (Thumbnail)
**Location:** `app/Filament/Resources/Bookings/Tables/BookingsTable.php`

- `ImageColumn::make('qr_code')` shows the QR thumbnail.
- Uses the `public` disk and generates a public URL from the stored path.

### Booking Preview (Form)
**Location:** `resources/views/filament/bookings/qr-form-preview.blade.php`

- Renders a PNG QR preview from the reference number using `QrCode::format('png')->size(200)`.
- Displays a helpful empty state while the reference number is not yet generated.

## QR Scanner (Admin UI)
**Location:** `app/Filament/Resources/Bookings/Pages/ListBookings.php`

A “Scan QR” action is available on the bookings list page.

**Flow:**
1. The action opens a form with `QrCodeInput`.
2. The scanner returns the raw payload string.
3. Payload is JSON-decoded and the booking is found by:
   - `booking_id` (preferred), or
   - `reference_number` (fallback)
4. On success, the user is redirected to the Booking edit page.
5. On failure, a Filament danger notification is shown.

## Storage & Public Access
- QR files are stored on the `public` disk.
- Ensure the storage symlink exists:
  - `php artisan storage:link`
- Public QR images are served from `public/storage/qr/bookings/...`.

## Testing Checklist
- Create a booking and verify `qr_code` is populated.
- Confirm QR image file exists under `storage/app/public/qr/bookings/`.
- Ensure the bookings table shows a QR thumbnail.
- Use the “Scan QR” action and verify it opens the correct booking.

## Troubleshooting
- **No QR thumbnail:** Verify the storage symlink and `qr_code` path.
- **Scanner not finding booking:** Confirm payload JSON fields (`booking_id`, `reference`) match the stored data.
- **Missing reference number:** Ensure booking creation runs normally (reference number is generated on `creating`).
