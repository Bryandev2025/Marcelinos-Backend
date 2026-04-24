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

## `GET /api/bookings/receipt/{token}` (and reference variant)

When the booking is **cancelled** and `payment_status` is **`refund_pending`** or **`refunded`**, and the guest **paid** something, the JSON includes **`cancellation_refund`**:

- `fee_percent` — cancellation deduction percentage (admin setting)
- `fee_from_total` — fee amount from booking total (PHP)
- `amount_paid` — total amount the guest paid (PHP)
- `retained` — non-refundable portion kept (PHP)
- `refund_to_guest` — amount the guest receives back after deduction (PHP)

The same figures appear on the **billing statement PDF** download for those bookings. The guest-facing receipt (Step 5) renders this block for full transparency.

## `PATCH /api/bookings/{booking}/cancel`

After OTP verification:

- `booking_status` is set to `cancelled`.
- If the booking was **partial** or **paid**, `payment_status` is set to **`refund_pending`** (unpaid bookings stay `unpaid`).
- Response `booking` reflects the updated row.
- Response `cancellation` includes:

  - `cancellation.fee_percent`
  - `cancellation.fee_from_total`
  - `cancellation.amount_paid`
  - `cancellation.amount_to_keep`
  - `cancellation.amount_to_refund`

The `cancellation` object is computed **before** the status update, using the active admin **Cancellation Deduction Percentage** (`cancellation_fee_percent`).

## 5a) Admin list & view (next step)

- **Bookings list** (`/admin/bookings/list`): the **Next step** column shows **Complete refund** when stay is cancelled and payment is **Refund pending**. Hover the cell for a tooltip with **fee %, fee amount, paid, retained, refund to guest**.
- **View / Edit booking** — **Front desk & payments** panel includes an orange **Cancellation — refund transparency** box (same figures) above the **Next:** line.

## 5) Staff/Admin process: Refund pending → Refunded

**Guest-cancel flow**

1. Guest completes OTP cancellation (`PATCH .../cancel`).
2. Booking is `cancelled`. If they had paid (partial or paid), `payment_status` is **`refund_pending`**.
3. Staff receive the refund alert email (when enabled). The email includes policy-based lines for **cancelled** bookings (deduction %, retained amount, amount to refund).
4. Staff process the refund externally using the policy breakdown (and `amount_to_refund` from the cancel API or admin context).
5. In Filament (bookings table, booking edit/view actions, or room calendar where applicable), use **Mark refund completed** after payout. The confirmation modal shows the current policy breakdown (for cancelled bookings) or overpayment summary (for **rescheduled** bookings still in `refund_pending`).
6. `payment_status` becomes **`refunded`**. Guest refund-completed email may be sent per notification settings.

**Reschedule overpayment flow** (unchanged in spirit)

- Shortening a stay can set `payment_status` to `refund_pending` while `booking_status` stays **`rescheduled`**. Staff complete the refund the same way (**Mark refund completed**).

Operational guidance:

- If `amount_to_refund = 0`, staff may still use **Mark refund completed** to close the pipeline when no cash movement is required.
- Filament **Edit booking** does not auto-recompute `payment_status` from paid amounts when status is `refund_pending` or `refunded`, so those states are not overwritten on save.

## 6) Current scope and limitations

- The global cancellation fee is configurable in admin settings.
- Per-booking manual override amount is **not implemented yet**.
- No automatic payment record mutation/refund transaction is posted at cancel time; staff confirm completion in admin after external refund.

## 7) Automated tests

- `tests/Feature/BookingGuestCancelRefundTest.php` — guest cancel sets `refund_pending` for paid bookings; unpaid stays unpaid; Edit booking preserves `refund_pending` when saving.

