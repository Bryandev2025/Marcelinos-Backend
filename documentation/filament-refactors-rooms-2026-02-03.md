# Rooms Refactors (2026-02-03)

## Purpose
Manage room inventory, pricing, availability, and amenities across admin and API endpoints.

## Data Model
- Model: `Room`
- Core fields: `name`, `capacity`, `type`, `price`, `status`.
- Relationships:
	- `Room` ↔ `Booking` (many-to-many via `booking_room`)
	- `Room` ↔ `Amenity` (many-to-many via `amenity_room`)
	- `Room` → `Review` (morphMany)
- Centralized options:
	- Room types.
- Availability scope:
	- `availableBetween($checkIn, $checkOut)` excludes rooms overlapping non-cancelled bookings.

## Migrations
- Rooms table creation.
- Amenity pivot table.

## Filament (Admin)
- Form:
	- Uses centralized type/status options.
	- Adds amenities multi-select.
	- Handles featured and gallery images via Spatie Media Library.
- Table:
	- Uses centralized type labels and status colors.
	- Shows featured image and core attributes.

## API / Controllers / Routes
- Controller: `RoomController` provides list and show.
- Routes registered in `routes/api.php`:
	- `GET /rooms`
	- `GET /rooms/{id}`
- Behavior:
	- `is_all=true` returns all rooms.
	- Otherwise requires `check_in`/`check_out` and uses availability scope.

## What it does
- Keeps room inventory consistent across admin and API.
- Supports availability filtering and amenity display for booking flows.

## Files
- app/Models/Room.php
- app/Filament/Resources/Rooms/Schemas/RoomForm.php
- app/Filament/Resources/Rooms/Tables/RoomsTable.php
- app/Http/Controllers/API/RoomController.php
- routes/api.php
- database/migrations/2026_01_14_064453_create_rooms_table.php
- database/migrations/2026_01_14_070210_create_amenity_room_table.php
