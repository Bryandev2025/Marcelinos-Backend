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

        $status = (string) $booking->status;

        if ($status === Booking::STATUS_CANCELLED) {
            return __('No further actions — booking is cancelled.');
        }

        if ($status === Booking::STATUS_COMPLETED) {
            return __('No further actions — stay is completed.');
        }

        if ($status === Booking::STATUS_OCCUPIED) {
            return __('Mark as completed when the guest has checked out.');
        }

        if ($status === Booking::STATUS_RESCHEDULED) {
            return __('Review dates and payment; then continue with payment or check-in as usual.');
        }

        $balance = (float) $booking->balance;
        $payAssessment = BookingFullBalancePayment::assess($booking);

        if (in_array($status, [Booking::STATUS_UNPAID, Booking::STATUS_PARTIAL], true)) {
            if ($balance <= 0.009) {
                return __('Balance is settled; status should move to Paid when payments are recorded correctly.');
            }

            if (! $payAssessment['allowed'] && $payAssessment['message']) {
                return $payAssessment['message'];
            }

            return __('Collect payment: add one or more cash amounts under Payments, or use “Settle remaining balance” to record the remainder and mark Paid in one step.');
        }

        if ($status === Booking::STATUS_PAID) {
            $checkIn = BookingCheckInEligibility::assess($booking);
            if ($checkIn['allowed']) {
                return __('Guest is fully paid and rooms/venues are ready — check in when they arrive.');
            }

            return $checkIn['message'] ?? __('Complete room and venue assignments before check-in.');
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

        $status = (string) $booking->status;

        if ($status === Booking::STATUS_CANCELLED) {
            return '—';
        }

        if ($status === Booking::STATUS_COMPLETED) {
            return '—';
        }

        if ($status === Booking::STATUS_OCCUPIED) {
            return __('Mark complete');
        }

        if ($status === Booking::STATUS_RESCHEDULED) {
            return __('Review');
        }

        $balance = (float) $booking->balance;

        if (in_array($status, [Booking::STATUS_UNPAID, Booking::STATUS_PARTIAL], true)) {
            if ($balance > 0.009) {
                $pay = BookingFullBalancePayment::assess($booking);
                if (! $pay['allowed']) {
                    return __('Assign rooms / fix payment');
                }

                return __('Collect balance');
            }

            return __('Verify payment');
        }

        if ($status === Booking::STATUS_PAID) {
            $checkIn = BookingCheckInEligibility::assess($booking);
            if ($checkIn['allowed']) {
                return __('Check in guest');
            }

            return __('Finish assignment');
        }

        return __('Review');
    }

    /**
     * Rich summary for the operations panel (status + amounts + next step).
     */
    public static function operationsSummaryHtml(Booking $booking): HtmlString
    {
        $booking->loadMissing(['guest']);

        $status = (string) $booking->status;
        $statusLabel = e(Booking::statusOptions()[$status] ?? $status);
        $color = collect(Booking::statusColors())->flip()->get($status, 'gray');

        $total = number_format((float) $booking->total_price, 2);
        $paid = number_format((float) $booking->total_paid, 2);
        $balance = number_format((float) $booking->balance, 2);

        $next = e(self::nextStepPlainText($booking));

        $paymentHint = e(__(
            'Payments tab: record one or more partial cash amounts. Settle remaining balance: records the full remainder in one step and sets status to Paid.',
        ));

        $html = <<<HTML
<div class="space-y-3 text-sm">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-gray-600 dark:text-gray-400">Status:</span>
        <span class="fi-badge flex items-center gap-x-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset fi-color-{$color}">
            {$statusLabel}
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
