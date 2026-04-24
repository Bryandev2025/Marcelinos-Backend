# Legacy Bookings CSV Import

Use this command to import old bookings (for example, 2025 records) into the current system:

```bash
php artisan bookings:import-legacy-csv "path/to/legacy-bookings.csv"
```

## Required CSV columns

- `first_name`
- `last_name`
- `email`
- `contact_num`
- `check_in`
- `check_out`
- `total_price`

The importer also accepts common aliases such as `guest_first_name`, `guest_email`, `phone`, `checkin`, `checkout`, and `amount`.

## Optional columns

- `middle_name`
- `status`
- `payment_method`
- `rooms` (separator: `|` or `;`, exact room names)
- `venues` (separator: `|` or `;`, exact venue names)
- `venue_event_type` (example: `wedding`, `birthday`, `meeting_staff`)

If `status` is missing, the importer auto-decides:

- `completed` if `check_out` is already in the past
- `occupied` if currently between `check_in` and `check_out`
- `paid` for future stays

## Useful options

- `--dry-run` validates the file without writing to the database.
- `--delimiter=";"` for semicolon-separated exports.
- `--default-status=paid` to force a status when CSV rows have no status.
- `--timezone=Asia/Manila` to control date parsing and status evaluation timezone.
- `--allow-duplicates` allows re-importing rows that match existing bookings.

By default, duplicates are skipped using guest/date/total fingerprint matching (production-safe default).

## Example

```bash
php artisan bookings:import-legacy-csv "storage/app/legacy-2025.csv" --dry-run
php artisan bookings:import-legacy-csv "storage/app/legacy-2025.csv"
```
