# Bookings Refactors (2026-02-03)

## Purpose
Manage guest bookings across rooms and venues, including availability checks, pricing, QR generation, and administrative workflows.

## Data Model
- Model: `Booking`
- Core fields: `guest_id`, `reference_number`, `check_in`, `check_out`, `no_of_days`, `total_price`, `status`, `qr_code`.
- Relationships:
	- `Booking` → `Guest` (belongsTo)
	- `Booking` ↔ `Room` (many-to-many via `booking_room`)
	- `Booking` ↔ `Venue` (many-to-many via `booking_venue`)
	- `Booking` → `Review` (hasMany)
- Automatic behavior:
	- Generates `reference_number` on create.
	- Generates QR code after create and stores it on `public` disk.
	- Updates room status on booking status transitions.

## Migrations
- Bookings table creation.
- Refactor to many rooms/venues with pivot tables:
	- booking_room
	- booking_venue

## Filament (Admin)
- Form:
	- Guest select is relationship-based using full name labels.
	- Rooms/Venues multi-selects with availability validation.
	- Automatic calculation of stay length and total price.
	- `total_price` is read-only (computed).
	- Status options centralized in `Booking`.
- Table:
	- Shows QR image, reference number, guest full name, rooms/venues.
	- Status badges use centralized status colors.
	- Status filter uses centralized options.
- Resource:
	- Eager loads guest/rooms/venues with selected columns.
	- Authorization handled by policy (resource method removed).

## API / Controllers / Routes
- Controller: `BookingController` provides listing, create, show, update, delete, cancel, and receipt by reference.
- Routes registered in `routes/api.php`:
	- `GET /bookings`
	- `POST /bookings`
	- `GET /bookings/{id}`
	- `PUT /bookings/{id}`
	- `DELETE /bookings/{id}`
	- `PATCH /bookings/{booking}/cancel`
	- `GET /booking-receipt/{reference}`
	- Additional `apiResource('/bookings/store', BookingController::class)` is also present.

## What it does
- Supports multi-room and multi-venue bookings.
- Enforces date validity and availability rules.
- Produces printable QR payloads for check-in flows.
- Centralizes status labels/colors for consistent UI.

## Files
- app/Models/Booking.php
- app/Filament/Resources/Bookings/BookingResource.php
- app/Filament/Resources/Bookings/Schemas/BookingForm.php
- app/Filament/Resources/Bookings/Tables/BookingsTable.php
- app/Http/Controllers/API/BookingController.php
- routes/api.php
- database/migrations/2026_01_14_064919_create_bookings_table.php
- database/migrations/2026_01_30_000000_refactor_bookings_to_many_rooms_venues.php
