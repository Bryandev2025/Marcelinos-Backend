# Cancellation & Refund Policy Workflow

This document explains the cancellation deduction and refund flow implemented in the backend, including where admins configure policy and how cancellation amounts are computed.

## 1) What was implemented

- Added a dynamic cancellation deduction setting in Admin Settings (`Settings > Payment Processing`).
- Added backend policy resolver: `app/Support/CancellationPolicy.php`.
- Exposed cancellation fee policy in payment settings API: `GET /api/payment-settings`.
- Added cancellation breakdown in booking cancel API response: `PATCH /api/bookings/{booking}/cancel`.
- Updated frontend policy/cancelation text to consume the dynamic backend value.

## 2) Admin configuration source of truth

### Setting key

- `cancellation_fee_percent` (integer `0..100`)

### Persisted in

- Cache (`payment_settings_config`) via `App\Filament\Pages\Settings`.
- Environment variable fallback: `PAYMENT_CANCELLATION_FEE_PERCENT` (default `30`).

### Resolver

- `App\Support\CancellationPolicy::feePercent()`
  - Reads from cache first.
  - Falls back to env if cache value is missing.
  - Clamps to `0..100`.

## 3) Cancellation math (current behavior)

When a booking is cancelled, backend computes:

- `fee_from_total = booking_total * (fee_percent / 100)`
- `amount_to_keep = min(amount_paid, fee_from_total)`
- `amount_to_refund = max(0, amount_paid - fee_from_total)`

This means:

- If guest paid less than the fee: keep all paid, refund `0`.
- If guest paid more than the fee: keep only fee amount, refund the excess.

Computation helper:

- `App\Support\CancellationPolicy::breakdown(float $bookingTotal, float $amountPaid): array`

## 4) API behavior

## `GET /api/payment-settings`

Returns:

- `online_payment_enabled`
- `partial_payment_options`
- `allow_custom_partial_payment`
- `cancellation_fee_percent`

## `PATCH /api/bookings/{booking}/cancel`

After OTP verification and status update to `cancelled`, response includes:

- `cancellation.fee_percent`
- `cancellation.fee_from_total`
- `cancellation.amount_paid`
- `cancellation.amount_to_keep`
- `cancellation.amount_to_refund`

## 5) Staff/Admin process for cancelled bookings

1. Open booking and trigger cancellation flow.
2. OTP is verified for cancellation.
3. Booking status changes to `cancelled`.
4. System computes cancellation breakdown using active fee percentage.
5. Staff/admin use `amount_to_refund` as the operational refund target.

Operational guidance:

- If `amount_to_refund = 0`, no refund processing is needed.
- If `amount_to_refund > 0`, process refund externally and record internal notes according to ops procedure.

## 6) Current scope and limitations

- The global cancellation fee is configurable in admin settings.
- Per-booking manual override amount is **not implemented yet**.
- No automatic payment record mutation/refund transaction is posted at cancel time; current implementation returns computed refund guidance values in API response.

## 7) Verification checklist

- `php -l` passed for:
  - `app/Filament/Pages/Settings.php`
  - `app/Http/Controllers/API/PaymentSettingsController.php`
  - `app/Support/CancellationPolicy.php`
  - `app/Http/Controllers/API/BookingController.php`
- React Doctor check passed (`100/100`) after frontend policy updates.

