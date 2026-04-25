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

Classification uses **amounts** (`total_price` vs `total_paid` / balance), not `payment_status` (which becomes `refund_pending` after cancel). Fully settled is when `max(0, total_price - total_paid) <= 0.01` (see `CancellationPolicy::BALANCE_SETTLED_EPSILON`).

### A) Not fully settled (partial payment at cancellation / deposit only)

- The entire amount paid is treated as a **non-refundable reservation fee**.
- `amount_to_refund = 0`, `amount_to_keep = amount_paid`, `fee_from_total = 0` (percentage policy does not apply to this case).
- Guest-facing text uses `statement_note` and `applies_cancellation_percent: false` / `settlement_type: partial_deposit`.

### B) Fully settled (full payment before cancel)

- `fee_from_total = booking_total * (fee_percent / 100)`
- `amount_to_keep = min(amount_paid, fee_from_total)`
- `amount_to_refund = max(0, amount_paid - fee_from_total)`

This matches the previous single-path behavior for **fully paid** bookings.

Computation:

- `App\Support\CancellationPolicy::breakdownForCancelledBooking(float $bookingTotal, float $amountPaid): array` — use this for **cancel** flows, receipts, and emails.
- `App\Support\CancellationPolicy::breakdown(float $bookingTotal, float $amountPaid): array` — still used **inside** the fully-settled branch for the percent math.

## 4) API behavior

## `GET /api/payment-settings`

Returns:

- `online_payment_enabled`
- `partial_payment_options`
- `allow_custom_partial_payment`
- `cancellation_fee_percent`

## `GET /api/bookings/receipt/{token}` (and reference variant)

When the booking is **cancelled** and `payment_status` is **`refund_pending`** or **`refunded`**, and the guest **paid** something, the JSON includes **`cancellation_refund`**:

- `fee_percent` — cancellation deduction percentage when **fully settled**; `0` when **partial_deposit** (not used for the fee)
- `fee_from_total` — fee amount from booking total (PHP) when **fully settled**; `0` for **partial_deposit**
- `amount_paid` — total amount the guest paid (PHP)
- `retained` — non-refundable portion kept (PHP)
- `refund_to_guest` — amount the guest receives back (PHP; **0** for **partial_deposit**)
- `applies_cancellation_percent` — `true` if admin **%** applies; `false` for **partial_deposit** / reservation-fee only
- `settlement_type` — `full_settlement` or `partial_deposit`
- `statement_note` — human-readable note for the guest (billing statement and receipt)

The same figures appear on the **billing statement PDF** download for those bookings. The guest-facing receipt (Step 5) renders this block for full transparency.

## `PATCH /api/bookings/{booking}/cancel`

After OTP verification:

- `booking_status` is set to `cancelled`.
- If the booking was **partial** or **paid**, `payment_status` is set to **`refund_pending`** (unpaid bookings stay `unpaid`).
- Response `booking` reflects the updated row.
- Response `cancellation` includes the result of `breakdownForCancelledBooking` (e.g. `amount_paid`, `amount_to_keep`, `amount_to_refund`, `settlement_type`, `applies_cancellation_percent`, `statement_note`, and when applicable the admin **Cancellation Deduction Percentage** via `fee_percent` / `fee_from_total`).

The `cancellation` object is computed **before** the status update.

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

