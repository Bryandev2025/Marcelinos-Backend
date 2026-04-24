<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Support\Facades\Cache;

final class CancellationPolicy
{
    public static function feePercent(): int
    {
        $cached = Cache::get('payment_settings_config');
        if (is_array($cached) && array_key_exists('cancellation_fee_percent', $cached)) {
            return self::normalizePercent($cached['cancellation_fee_percent']);
        }

        return self::normalizePercent(env('PAYMENT_CANCELLATION_FEE_PERCENT', 30));
    }

    /**
     * @return array{
     *   fee_percent:int,
     *   fee_from_total:float,
     *   amount_paid:float,
     *   amount_to_keep:float,
     *   amount_to_refund:float
     * }
     */
    public static function breakdown(float $bookingTotal, float $amountPaid): array
    {
        $feePercent = self::feePercent();
        $normalizedTotal = max(0, round($bookingTotal, 2));
        $normalizedPaid = max(0, round($amountPaid, 2));
        $feeFromTotal = round(($normalizedTotal * $feePercent) / 100, 2);
        $amountToKeep = round(min($normalizedPaid, $feeFromTotal), 2);
        $amountToRefund = round(max(0, $normalizedPaid - $feeFromTotal), 2);

        return [
            'fee_percent' => $feePercent,
            'fee_from_total' => $feeFromTotal,
            'amount_paid' => $normalizedPaid,
            'amount_to_keep' => $amountToKeep,
            'amount_to_refund' => $amountToRefund,
        ];
    }

    /**
     * When a booking is cancelled and in the guest refund pipeline with money paid,
     * return amounts for receipts, PDFs, and admin “next step” / list hints.
     *
     * @return array{
     *   fee_percent:int,
     *   fee_from_total:float,
     *   amount_paid:float,
     *   retained:float,
     *   refund_to_guest:float
     * }|null
     */
    public static function cancelledBookingRefundTransparency(Booking $booking): ?array
    {
        if ((string) $booking->booking_status !== Booking::BOOKING_STATUS_CANCELLED) {
            return null;
        }

        if (! in_array((string) $booking->payment_status, [
            Booking::PAYMENT_STATUS_REFUND_PENDING,
            Booking::PAYMENT_STATUS_REFUNDED,
        ], true)) {
            return null;
        }

        if ((float) $booking->total_paid <= 0.009) {
            return null;
        }

        $row = self::breakdown((float) $booking->total_price, (float) $booking->total_paid);

        return [
            'fee_percent' => $row['fee_percent'],
            'fee_from_total' => $row['fee_from_total'],
            'amount_paid' => $row['amount_paid'],
            'retained' => $row['amount_to_keep'],
            'refund_to_guest' => $row['amount_to_refund'],
        ];
    }

    /**
     * Human-readable lines for admin "mark refund completed" confirmation.
     */
    public static function adminMarkRefundCompletedModalBody(Booking $booking): string
    {
        $suffix = 'Use this only after the guest refund has been processed externally. This updates payment status to Refunded.';

        if ((string) $booking->booking_status === Booking::BOOKING_STATUS_CANCELLED) {
            $row = self::breakdown((float) $booking->total_price, (float) $booking->total_paid);

            return 'Per cancellation policy (current admin setting): '.$row['fee_percent'].'% deduction on booking total '
                .'(PHP '.number_format($row['fee_from_total'], 2).'). '
                .'Amount retained: PHP '.number_format($row['amount_to_keep'], 2).'. '
                .'Amount to refund: PHP '.number_format($row['amount_to_refund'], 2).".\n\n"
                .$suffix;
        }

        $totalPaid = (float) $booking->total_paid;
        $totalPrice = (float) $booking->total_price;
        $overage = max(0, round($totalPaid - $totalPrice, 2));

        return 'After reschedule, total paid is PHP '.number_format($totalPaid, 2)
            .' and the new booking total is PHP '.number_format($totalPrice, 2)
            .'. Estimated overpayment to refund: PHP '.number_format($overage, 2).".\n\n"
            .$suffix;
    }

    private static function normalizePercent(mixed $raw): int
    {
        $value = (int) $raw;

        return max(0, min(100, $value));
    }
}
