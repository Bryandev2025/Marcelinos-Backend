# Reviews

## Overview
Reviews support three cases:
- **Room reviews** (guest reviews a room)
- **Venue reviews** (guest reviews a venue)
- **Site reviews** (overall website feedback)

Guests do not have accounts, so reviews are tied to `guest_id` (and optionally `booking_id`).

## Data Model
`reviews` table fields:
- `guest_id` (required)
- `booking_id` (optional)
- `reviewable_type` + `reviewable_id` (nullable polymorphic target: room/venue)
- `is_site_review` (boolean)
- `rating` (1–5)
- `title` (nullable)
- `comment` (nullable)
- `is_approved` (boolean)
- `reviewed_at` (nullable datetime)

### Rules
- If `is_site_review = true`, `reviewable_type` and `reviewable_id` are `null`.
- For room/venue reviews, `is_site_review = false` and `reviewable_type/id` are required.

## Relationships
- `Guest` has many `Review`
- `Booking` has many `Review`
- `Room` morphMany `Review`
- `Venue` morphMany `Review`
- `Review` morphTo `reviewable`

## Filament Admin
### Resource
- Reviews are managed in **Reviews** resource.
- Filters: site review, approved, rating.

### Relation Managers
Visible as a **Reviews** tab on:
- Room
- Venue
- Guest
- Booking

## Recommended Flow (no guest account)
1. After a booking is completed, allow the guest to submit a review via a form or link.
2. Save the review with `guest_id` and `booking_id`.
3. For room/venue feedback, set `reviewable_type/id`.
4. For site feedback, set `is_site_review = true`.
5. Use `is_approved` to moderate what appears publicly.
