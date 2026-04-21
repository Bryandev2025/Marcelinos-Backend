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

        $total = number_format((float) $booking->total_price, 2);
        $paid = number_format((float) $booking->total_paid, 2);
        $balance = number_format((float) $booking->balance, 2);

        $next = e(self::nextStepPlainText($booking));

        $paymentHint = e(__(
            'Payments tab: record one or more partial cash amounts. Settle remaining balance: records the full remainder in one step and sets payment to Paid.',
        ));

        $html = <<<HTML
<div class="space-y-3 text-sm">
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
        <div><dt class="text-gray-500 dark:text-gray-400">Paid</dt><dd class="font-medium tabular-nums">₱{$paid}</dd></div>
        <div><dt class="text-gray-500 dark:text-gray-400">Balance</dt><dd class="font-medium tabular-nums">₱{$balance}</dd></div>
    </dl>
    <p class="rounded-lg bg-gray-50 p-3 text-gray-800 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10"><span class="font-medium text-gray-950 dark:text-white">Next:</span> {$next}</p>
    <p class="text-xs text-gray-500 dark:text-gray-400">{$paymentHint}</p>
</div>
HTML;

        return new HtmlString($html);
    }
}
