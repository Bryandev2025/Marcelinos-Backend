# Venues Refactors (2026-02-03)

## Purpose
Manage venue inventory, pricing, availability, media, and amenities across admin and API endpoints.

## Data Model
- Model: `Venue`
- Core fields: `name`, `description`, `capacity`, `price`, `status`.
- Relationships:
	- `Venue` ↔ `Booking` (many-to-many via `booking_venue`)
	- `Venue` ↔ `Amenity` (many-to-many via `amenity_venue`)
	- `Venue` → `Review` (morphMany)
- Centralized options:
	- Status options and badge colors.
- Availability scope:
	- `availableBetween($checkIn, $checkOut)` excludes venues overlapping non-cancelled bookings.
- Media:
	- Featured image and gallery collections via Spatie Media Library.

## Migrations
- Venues table creation.
- Added `status` column with default `available`.
- Amenity pivot table.

## Filament (Admin)
- Form:
	- Uses centralized status options.
	- Amenities multi-select.
	- Featured and gallery images using Spatie Media Library.
- Table:
	- Spatie media column for featured image.
	- Status badges use centralized colors/labels.

## API / Controllers / Routes
- Controller: `VenueController` provides list and show.
- Routes registered in `routes/api.php`:
	- `GET /venues`
	- `GET /venues/{id}`
	- `Route::apiResource('venues', VenueController::class)` also exists.
- Behavior:
	- `is_all=true` returns all venues.
	- Otherwise requires `check_in`/`check_out` and uses availability scope.

## What it does
- Keeps venue inventory consistent across admin and API.
- Supports availability filtering, amenities, and media display for booking flows.

## Files
- app/Models/Venue.php
- app/Filament/Resources/Venues/Schemas/VenuesForm.php
- app/Filament/Resources/Venues/Tables/VenuesTable.php
- app/Http/Controllers/API/VenueController.php
- routes/api.php
- database/migrations/2026_01_14_064454_create_venues_table.php
- database/migrations/2026_02_03_000000_add_status_to_venues_table.php
- database/migrations/2026_01_20_000001_create_amenity_venue_table.php
