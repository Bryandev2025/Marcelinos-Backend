# Guests Refactors (2026-02-03)

## Purpose
Manage guest profiles used across bookings, reviews, and admin workflows.

## Data Model
- Model: `Guest`
- Core fields: name, contact, email, gender, `is_international`, location fields.
- Relationships:
	- `Guest` → `Booking` (hasMany)
	- `Guest` → `Review` (hasMany)
	- `Guest` → `Image` (morphOne identification; if used)
- Computed:
	- `full_name` accessor.

## Migrations
- Guests table with defaults for `is_international` and `country`.

## Filament (Admin)
- Form:
	- Email is required and unique (ignores current record on edit).
	- Gender options centralized in `Guest`.
	- `is_international` toggle controls visibility/requirements of address fields.
- Table:
	- Displays full name (searchable by first/middle/last).
	- Gender badge uses centralized labels/colors.
	- International filter added.
	- Address columns are toggleable to reduce noise.
- Resource:
	- Record title uses `full_name`.

## API / Controllers / Routes
- Controller: `GuestController` supports `index`, `store`, and `destroy`.
- No guest routes are currently registered in `routes/api.php`.

## What it does
- Ensures data consistency for guest identity and location.
- Improves admin UX with clearer filtering and conditional fields.

## Files
- app/Models/Guest.php
- app/Filament/Resources/Guests/Schemas/GuestForm.php
- app/Filament/Resources/Guests/GuestResource.php
- app/Filament/Resources/Guests/Tables/GuestsTable.php
- app/Http/Controllers/API/GuestController.php
- routes/api.php
- database/migrations/2026_01_14_064410_create_guests_table.php
