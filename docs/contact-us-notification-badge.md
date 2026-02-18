# Contact Us Panel Notification Badge

## Overview
This feature adds a notification badge to the Contact Us panel in the Filament admin dashboard. The badge displays the number of new contact requests, drawing the admin's attention to pending inquiries.

## How It Works
- The badge appears in the sidebar navigation next to the Contact Us panel.
- It shows the count of contact requests where the `status` is `new`.
- If there are no new requests, the badge is hidden.

## Implementation Details
- The `ContactUsResource` class implements the `getNavigationBadge()` method:

```php
public static function getNavigationBadge(): ?string
{
    $count = ContactUs::where('status', 'new')->count();
    return $count > 0 ? (string) $count : null;
}
```
- The `status` field is an ENUM with values: `new`, `in_progress`, `resolved`, `closed`.
- Only records with `status = 'new'` are counted for the badge.

## Usage
1. When a new contact request is submitted, its status is set to `new` by default.
2. The admin will see a badge with the count of new requests in the sidebar.
3. Once the status of a request is changed (e.g., to `in_progress`), it is no longer counted in the badge.

## Customization
- To change which statuses are counted, modify the query in `getNavigationBadge()`.
- To change the badge's appearance, refer to Filament's resource navigation customization options.

## Troubleshooting
- If the badge does not appear, ensure there are records with `status = 'new'` in the `contact_us` table.
- Make sure the `getNavigationBadge()` method is present in the `ContactUsResource` class.

---

_Last updated: 2026-02-13_