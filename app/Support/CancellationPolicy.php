<?php

namespace App\Support;

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

    private static function normalizePercent(mixed $raw): int
    {
        $value = (int) $raw;

        return max(0, min(100, $value));
    }
}
