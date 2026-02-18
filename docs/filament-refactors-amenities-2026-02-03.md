# Amenities Refactors (2026-02-03)

## Purpose
Provide a central, reusable list of amenities that can be assigned to rooms and venues in Filament.

## Data Model
- Model: `Amenity` with `name` field (unique).
- Relationships:
	- `Amenity` ↔ `Room` (many-to-many)
	- `Amenity` ↔ `Venue` (many-to-many)

## Migrations
- Create amenities table: `name` (unique).
- Pivot tables:
	- amenity_room
	- amenity_venue

## Filament (Admin)
- Resource created with full CRUD.
- Form:
	- `name` required, unique (ignores current record on edit).
- Table:
	- `name`
	- `rooms_count`, `venues_count`
	- created/updated timestamps (toggleable)
- Added amenities multiselects to Room and Venue forms for assignment.

## API / Controllers / Routes
- Controller exists but is currently empty: no API behavior yet.
- No API routes registered for amenities in `routes/api.php`.

## What it does
- Enables admins to create and manage amenities once, then attach them to rooms and venues.
- Exposes counts of where each amenity is used for quick auditing.

## Files
- app/Filament/Resources/Amenities/AmenityResource.php
- app/Filament/Resources/Amenities/Schemas/AmenityForm.php
- app/Filament/Resources/Amenities/Tables/AmenitiesTable.php
- app/Filament/Resources/Amenities/Pages/ListAmenities.php
- app/Filament/Resources/Amenities/Pages/CreateAmenity.php
- app/Filament/Resources/Amenities/Pages/EditAmenity.php
- app/Models/Amenity.php
- app/Filament/Resources/Rooms/Schemas/RoomForm.php
- app/Filament/Resources/Venues/Schemas/VenuesForm.php
- database/migrations/2026_01_14_070209_create_amenities_table.php
- database/migrations/2026_01_14_070210_create_amenity_room_table.php
- database/migrations/2026_01_20_000001_create_amenity_venue_table.php
- app/Http/Controllers/API/AmenityController.php
- routes/api.php
