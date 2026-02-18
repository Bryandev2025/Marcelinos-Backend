# Reviews Refactors (2026-02-03)

## Purpose
Capture and moderate guest feedback for rooms, venues, and the overall site.

## Data Model
- Model: `Review`
- Core fields: `guest_id`, `booking_id`, `reviewable_type`, `reviewable_id`, `rating`, `title`, `comment`, `is_site_review`, `is_approved`, `reviewed_at`.
- Relationships:
	- `Review` → `Guest` (belongsTo)
	- `Review` → `Booking` (belongsTo)
	- `Review` → `reviewable` (morphTo: Room or Venue)
- Centralized options:
	- Rating options
	- Reviewable type options

## Migrations
- Reviews table created for polymorphic review targets.

## Filament (Admin)
- Form:
	- Guest and booking fields use relationship selects.
	- Review type and target are dynamic (hidden when site review is on).
	- Avoids preloading all targets to keep it scalable.
- Table:
	- Displays full guest name.
	- Rating badges and filters use centralized options.
	- `is_approved` toggle for moderation.
- Resource:
	- Eager loads only needed columns for guest/booking.

## API / Controllers / Routes
- No dedicated API controller or routes for reviews are currently registered in `routes/api.php`.

## What it does
- Unifies room/venue/site review logic under one model.
- Provides moderation controls and searchable admin review listings.

## Files
- app/Models/Review.php
- app/Filament/Resources/Reviews/Schemas/ReviewForm.php
- app/Filament/Resources/Reviews/Tables/ReviewsTable.php
- app/Filament/Resources/Reviews/ReviewResource.php
- routes/api.php
- database/migrations/2026_02_03_000000_create_reviews_table.php
