<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Support\HtmlString;

/**
 * Staff-facing copy for “what’s next” on a booking (list column, operations panel).
 */
final class BookingAdminGuidance
{
    public static function nextStepPlainText(Booking $booking): string
    {
        if ($booking->trashed()) {
            return __('Restore this booking from the recycle bin to manage it.');
        }

        $bookingStatus = (string) $booking->booking_status;
        $paymentStatus = (string) $booking->payment_status;

        if ($bookingStatus === Booking::BOOKING_STATUS_CANCELLED) {
            $row = CancellationPolicy::cancelledBookingRefundTransparency($booking);
            if ($row === null) {
                return __('No further actions — booking is cancelled.');
            }

            $partialDeposit = empty($row['applies_cancellation_percent']);

            if ($paymentStatus === Booking::PAYMENT_STATUS_REFUND_PENDING) {
                if ($partialDeposit) {
                    return __('Refund pending: no payout to the guest — their partial payment (PHP :paid) is retained as a non-refundable reservation fee. Use “Mark refund completed” to close the pipeline when appropriate.', [
                        'paid' => number_format($row['amount_paid'], 2),
                    ]);
                }

                return __('Refund pending: pay out PHP :refund to the guest after deduction. Cancellation fee is :pct% of booking total (PHP :fee); PHP :retained retained (non-refundable); they paid PHP :paid. Then use “Mark refund completed.”', [
                    'refund' => number_format($row['refund_to_guest'], 2),
                    'pct' => (string) $row['fee_percent'],
                    'fee' => number_format($row['fee_from_total'], 2),
                    'retained' => number_format($row['retained'], 2),
                    'paid' => number_format($row['amount_paid'], 2),
                ]);
            }

            if ($paymentStatus === Booking::PAYMENT_STATUS_REFUNDED) {
                if ($partialDeposit) {
                    return __('Refund pipeline closed. Partial payment (PHP :paid) was retained as a non-refundable reservation fee; no guest refund was due.', [
                        'paid' => number_format($row['amount_paid'], 2),
                    ]);
                }

                return __('Refund completed. Per policy: :pct% fee (PHP :fee), PHP :retained retained, guest refund PHP :refund.', [
                    'pct' => (string) $row['fee_percent'],
                    'fee' => number_format($row['fee_from_total'], 2),
                    'retained' => number_format($row['retained'], 2),
                    'refund' => number_format($row['refund_to_guest'], 2),
                ]);
            }

            if ($paymentStatus === Booking::PAYMENT_STATUS_NON_REFUNDABLE) {
                return __('Non-refundable: no guest payout is due. Amount retained: PHP :retained from paid PHP :paid.', [
                    'retained' => number_format($row['retained'], 2),
                    'paid' => number_format($row['amount_paid'], 2),
                ]);
            }

            return __('No further actions — booking is cancelled.');
        }

        if ($bookingStatus === Booking::BOOKING_STATUS_COMPLETED) {
            return __('No further actions — stay is completed.');
        }

        if ($bookingStatus === Booking::BOOKING_STATUS_OCCUPIED) {
            return __('Mark as completed when the guest has checked out.');
        }

        if ($bookingStatus === Booking::BOOKING_STATUS_RESCHEDULED) {
            return __('Review dates and payment; then continue with payment or check-in as usual.');
        }

        $balance = (float) $booking->balance;
        $payAssessment = BookingFullBalancePayment::assess($booking);

        if ($bookingStatus === Booking::BOOKING_STATUS_RESERVED) {
            if (in_array($paymentStatus, [Booking::PAYMENT_STATUS_UNPAID, Booking::PAYMENT_STATUS_PARTIAL], true)) {
                if ($balance <= 0.009) {
                    return __('Balance is settled; payment status should be Paid when payments are recorded correctly.');
                }

                if (! $payAssessment['allowed'] && $payAssessment['message']) {
                    return $payAssessment['message'];
                }

                return __('Collect payment: add one or more cash amounts under Payments, or use “Settle remaining balance” to record the remainder and mark Paid in one step.');
            }

            if ($paymentStatus === Booking::PAYMENT_STATUS_PAID) {
                $checkIn = BookingCheckInEligibility::assess($booking);
                if ($checkIn['allowed']) {
                    return __('Guest is fully paid and rooms/venues are ready — check in when they arrive.');
                }

                return $checkIn['message'] ?? __('Complete room and venue assignments before check-in.');
            }
        }

        return __('Review booking details and payments.');
    }

    /**
     * Extra detail for the list “Next step” column tooltip (cancelled + refund pipeline).
     */
    public static function listNextStepTooltip(Booking $booking): ?string
    {
        $row = CancellationPolicy::cancelledBookingRefundTransparency($booking);
        if ($row === null) {
            return null;
        }

        if (empty($row['applies_cancellation_percent'])) {
            return __('Partial payment at cancellation: non-refundable reservation fee. Guest paid ₱:paid. Retained ₱:retained. Refund ₱:refund.', [
                'paid' => number_format($row['amount_paid'], 2),
                'retained' => number_format($row['retained'], 2),
                'refund' => number_format($row['refund_to_guest'], 2),
            ]);
        }

        return __(':pct% fee on total (₱:fee). Guest paid ₱:paid. Retained ₱:retained. Refund ₱:refund.', [
            'pct' => (string) $row['fee_percent'],
            'fee' => number_format($row['fee_from_total'], 2),
            'paid' => number_format($row['amount_paid'], 2),
            'retained' => number_format($row['retained'], 2),
            'refund' => number_format($row['refund_to_guest'], 2),
        ]);
    }

    /**
     * Short label for the optional list “Next action” column.
     */
    public static function listNextActionLabel(Booking $booking): string
    {
        if ($booking->trashed()) {
            return __('Restore');
        }

        $bookingStatus = (string) $booking->booking_status;
        $paymentStatus = (string) $booking->payment_status;

        if ($bookingStatus === Booking::BOOKING_STATUS_CANCELLED) {
            if ($paymentStatus === Booking::PAYMENT_STATUS_REFUND_PENDING) {
                return __('Complete refund');
            }

            return '—';
        }

        if ($bookingStatus === Booking::BOOKING_STATUS_COMPLETED) {
            return '—';
        }

        if ($bookingStatus === Booking::BOOKING_STATUS_OCCUPIED) {
            return __('Mark complete');
        }

        if ($bookingStatus === Booking::BOOKING_STATUS_RESCHEDULED) {
            return __('Review');
        }

        $balance = (float) $booking->balance;

        if ($bookingStatus === Booking::BOOKING_STATUS_RESERVED) {
            if (in_array($paymentStatus, [Booking::PAYMENT_STATUS_UNPAID, Booking::PAYMENT_STATUS_PARTIAL], true)) {
                if ($balance > 0.009) {
                    $pay = BookingFullBalancePayment::assess($booking);
                    if (! $pay['allowed']) {
                        return __('Assign rooms / fix payment');
                    }

                    return __('Collect balance');
                }

                return __('Verify payment');
            }

            if ($paymentStatus === Booking::PAYMENT_STATUS_PAID) {
                $checkIn = BookingCheckInEligibility::assess($booking);
                if ($checkIn['allowed']) {
                    return __('Check in guest');
                }

                return __('Finish assignment');
            }
        }

        return __('Review');
    }

    /**
     * Rich summary for the operations panel (status + amounts + next step).
     */
    public static function operationsSummaryHtml(Booking $booking): HtmlString
    {
        $booking->loadMissing(['guest']);

        $bs = (string) $booking->booking_status;
        $ps = (string) $booking->payment_status;
        $bookingLabel = e(Booking::bookingStatusOptions()[$bs] ?? $bs);
        $paymentLabel = e(Booking::paymentStatusOptions()[$ps] ?? $ps);
        $bookingColor = collect(Booking::bookingStatusColors())->flip()->get($bs, 'gray');
        $paymentColor = collect(Booking::paymentStatusColors())->flip()->get($ps, 'gray');

        $gross = BookingSpecialDiscount::grossTotal($booking);
        $discount = BookingSpecialDiscount::discountAmount($booking);
        $net = (float) $booking->total_price;

        $total = number_format($net, 2);
        $grossText = number_format($gross, 2);
        $discountText = number_format($discount, 2);
        $paid = number_format((float) $booking->total_paid, 2);
        $balance = number_format((float) $booking->balance, 2);

        $next = e(self::nextStepPlainText($booking));

        $cancellationHtml = '';
        $cr = CancellationPolicy::cancelledBookingRefundTransparency($booking);
        if ($cr !== null) {
            $paidCr = e(number_format($cr['amount_paid'], 2));
            $retained = e(number_format($cr['retained'], 2));
            $refund = e(number_format($cr['refund_to_guest'], 2));
            if (empty($cr['applies_cancellation_percent'])) {
                $note = e((string) ($cr['statement_note'] ?? CancellationPolicy::statementNotePartialDepositNonRefundable()));
                $cancellationHtml = <<<HTML
    <div class="rounded-lg border border-orange-200 bg-orange-50 p-3 text-sm ring-1 ring-orange-900/5 dark:border-orange-400/25 dark:bg-orange-950/30 dark:ring-white/10">
        <p class="font-semibold text-orange-950 dark:text-orange-100">Cancellation — refund transparency</p>
        <p class="mt-1 text-xs text-orange-900/90 dark:text-orange-100/80">{$note}</p>
        <dl class="mt-2 grid gap-1 sm:grid-cols-2 text-xs sm:text-sm">
            <div><dt class="text-orange-800/80 dark:text-orange-200/80">Amount guest paid (partial / reservation)</dt><dd class="font-medium tabular-nums text-orange-950 dark:text-orange-50">₱{$paidCr}</dd></div>
            <div><dt class="text-orange-800/80 dark:text-orange-200/80">Non-refundable (reservation fee)</dt><dd class="font-medium tabular-nums text-orange-950 dark:text-orange-50">₱{$retained}</dd></div>
            <div class="sm:col-span-2"><dt class="text-orange-800/80 dark:text-orange-200/80">Refund to guest</dt><dd class="font-semibold tabular-nums text-orange-950 dark:text-orange-50">₱{$refund}</dd></div>
        </dl>
    </div>
HTML;
            } else {
                $pct = e((string) $cr['fee_percent']);
                $fee = e(number_format($cr['fee_from_total'], 2));
                $cancellationHtml = <<<HTML
    <div class="rounded-lg border border-orange-200 bg-orange-50 p-3 text-sm ring-1 ring-orange-900/5 dark:border-orange-400/25 dark:bg-orange-950/30 dark:ring-white/10">
        <p class="font-semibold text-orange-950 dark:text-orange-100">Cancellation — refund transparency</p>
        <p class="mt-1 text-xs text-orange-900/90 dark:text-orange-100/80">Policy deduction: <strong>{$pct}%</strong> of booking total (fee PHP <span class="tabular-nums">{$fee}</span>).</p>
        <dl class="mt-2 grid gap-1 sm:grid-cols-2 text-xs sm:text-sm">
            <div><dt class="text-orange-800/80 dark:text-orange-200/80">Amount guest paid</dt><dd class="font-medium tabular-nums text-orange-950 dark:text-orange-50">₱{$paidCr}</dd></div>
            <div><dt class="text-orange-800/80 dark:text-orange-200/80">Deducted / retained</dt><dd class="font-medium tabular-nums text-orange-950 dark:text-orange-50">₱{$retained}</dd></div>
            <div class="sm:col-span-2"><dt class="text-orange-800/80 dark:text-orange-200/80">Refund to guest (after deduction)</dt><dd class="font-semibold tabular-nums text-orange-950 dark:text-orange-50">₱{$refund}</dd></div>
        </dl>
    </div>
HTML;
            }
        }

        $discountRow = $discount > 0.009
            ? '<div><dt class="text-gray-500 dark:text-gray-400">Gross</dt><dd class="font-medium tabular-nums">₱'.$grossText.'</dd></div>
               <div><dt class="text-gray-500 dark:text-gray-400">Discount</dt><dd class="font-medium tabular-nums text-rose-700 dark:text-rose-300">- ₱'.$discountText.'</dd></div>'
            : '';

        $html = <<<HTML
<div class="space-y-2 text-sm">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-gray-600 dark:text-gray-400">Stay:</span>
        <span class="fi-badge flex items-center gap-x-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset fi-color-{$bookingColor}">
            {$bookingLabel}
        </span>
        <span class="text-gray-600 dark:text-gray-400">Payment:</span>
        <span class="fi-badge flex items-center gap-x-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset fi-color-{$paymentColor}">
            {$paymentLabel}
        </span>
    </div>
    <dl class="grid gap-1 sm:grid-cols-3">
        <div><dt class="text-gray-500 dark:text-gray-400">Total</dt><dd class="font-medium tabular-nums">₱{$total}</dd></div>
        {$discountRow}
        <div><dt class="text-gray-500 dark:text-gray-400">Paid</dt><dd class="font-medium tabular-nums">₱{$paid}</dd></div>
        <div><dt class="text-gray-500 dark:text-gray-400">Balance</dt><dd class="font-medium tabular-nums">₱{$balance}</dd></div>
    </dl>
    {$cancellationHtml}
    <p class="rounded-lg bg-gray-50 p-3 text-gray-800 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10"><span class="font-medium text-gray-950 dark:text-white">Next:</span> {$next}</p>
</div>
HTML;

        return new HtmlString($html);
    }
}
