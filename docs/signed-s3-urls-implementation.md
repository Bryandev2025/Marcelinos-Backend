# Signed S3 URLs Implementation (Laravel + React)

## Purpose
This document describes the signed S3 URL implementation for media delivery in the Marcelino’s backend, enabling private buckets while allowing the frontend to render images directly from S3.

## What changed
- Media URLs for rooms and venues are now resolved to temporary signed S3 URLs when the media disk is `s3`.
- For non-S3 disks, the standard public URL is returned.
- API responses now return signed URLs in the `featured_image` and `gallery` fields.

## Backend implementation
### Media URL accessors
Signed URLs are generated in model accessors:
- [app/Models/Room.php](app/Models/Room.php)
- [app/Models/Venue.php](app/Models/Venue.php)

Accessors added:
- `featured_image_url`
- `gallery_urls`

### API responses
Controllers now return the signed URL fields:
- [app/Http/Controllers/API/RoomController.php](app/Http/Controllers/API/RoomController.php)
- [app/Http/Controllers/API/VenueController.php](app/Http/Controllers/API/VenueController.php)

## Configuration
Signed URL TTL is controlled by:
- `MEDIA_TEMPORARY_URL_DEFAULT_LIFETIME` (minutes)

See:
- [config/media-library.php](config/media-library.php)

## Frontend usage (React)
The frontend should render the returned URLs directly:
- `featured_image` for the main image
- `gallery` as an array of image URLs

No headers or AWS credentials are required in the browser.

## How to verify
1. Call the rooms or venues API endpoint.
2. Confirm `featured_image` and `gallery` URLs contain `X-Amz-Signature` and `X-Amz-Expires`.
3. Open the URL in a browser — the image should load successfully.
4. After the TTL expires, the same URL should return 403 (expired).

## Notes
- Signed URLs are short-lived by design. The frontend should refresh data on page load or refetch when needed.
- If using a CDN later, CloudFront can also be configured with signed URLs.
