# Billing Statement PDF Download (Backend + Frontend Integration)

This guide documents how the billing statement PDF download works end-to-end.

## Backend Entry Point

### Route

- File: `routes/api.php`
- Endpoint:
    - `GET /bookings/{booking:reference_number}/billing-statement/pdf`
- Middleware:
    - API key guard (`EnsureApiKeyIsValid` group)
    - `throttle:receipt_lookup`

### Signed Download Route

- File: `routes/web.php`
- Endpoint:
    - `GET /billing-statements/{booking:reference_number}/pdf`
- Middleware:
    - `signed`
    - `throttle:receipt_lookup`

### Controller Method

- File: `app/Http/Controllers/API/BookingController.php`
- Method: `downloadBillingStatementPdf(string $token)`

Method responsibilities:

1. Resolve booking via `findReceiptBooking($token)`
2. Return 404 when booking does not exist
3. Block pending verification booking states
4. Build payload via `buildBillingStatementData(...)`
5. Generate PDF using DomPDF view `billing-statements.step5`
6. Return downloadable PDF response

### Receipt Payload Field

- File: `app/Http/Controllers/API/BookingController.php`
- JSON field:
    - `billing_statement_pdf_url`

The receipt payload now includes a temporary signed URL that points to the web route above. Clients should prefer this URL when present and fall back to the legacy API download route when it is missing.

## PDF View

- File: `resources/views/billing-statements/step5.blade.php`

Template contents include:

- Booking statement header and metadata
- Guest/account/remittance sections
- Booking and payment summaries
- Itemized room/venue lines
- Totals and balance section
- Friendly next-step note and optional Messenger CTA
- Centered QR section
- Friendly guest-facing wording

## Data Builder

- Method: `buildBillingStatementData(Booking $booking)`

Important generated fields:

- Booking labels (`bookingTypeLabel`, status/payment labels)
- Computed room/venue subtotals and grand totals
- Amount paid and balance
- Check-in/check-out and issued timestamps
- QR image data URI
- Deposit percent and amount
- Deposit due label
- Messenger link with prefilled message

## Messenger URL Configuration

- Method: `messengerChatUrl()`
- Env key:
    - `FRONTEND_MESSENGER_CHAT_URL`
- Fallback:
    - `https://m.me/61557457680496`

## Frontend Consumer

- File: `client-marcelinos/src/pages/Booking/Steps/Step5.tsx`
- Prefers `booking.billing_statement_pdf_url` when present
- Falls back to the legacy API endpoint when the signed URL is missing
- Expects `Blob`
- Handles both desktop download and mobile open-in-tab flow
- Button state behavior:
    - `Download Receipt` -> `Generating PDF...` -> `Downloaded`

## Validation Command

After editing the Blade view:

- `php artisan view:cache`

Expected output:

- Blade templates cache successfully

## Operational Notes

- PDF is generated from backend records, not frontend-rendered HTML
- Route throttling protects endpoint from abuse
- Pending verification bookings are intentionally blocked from PDF issuance
- Keep user-facing text in the template non-technical and guest friendly
- Temporary signed URLs are generated from `BOOKING_BILLING_STATEMENT_URL_TTL_HOURS` and default to 24 hours
