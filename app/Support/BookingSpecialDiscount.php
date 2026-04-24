<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Support\ActivityLogger;

final class BookingSpecialDiscount
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    /**
     * @return array{allowed: bool, reason: string, message?: string}
     */
    public static function assessCanMutate(Booking $booking, ?User $actor): array
    {
        if ($booking->trashed()) {
            return ['allowed' => false, 'reason' => 'trashed', 'message' => 'Restore this booking to apply a discount.'];
        }

        if (! $actor || ! in_array((string) $actor->role, ['admin', 'staff'], true)) {
            return ['allowed' => false, 'reason' => 'forbidden', 'message' => 'You are not allowed to apply special discounts.'];
        }

        if (in_array((string) $booking->booking_status, [Booking::BOOKING_STATUS_CANCELLED, Booking::BOOKING_STATUS_COMPLETED], true)) {
            return ['allowed' => false, 'reason' => 'finalized', 'message' => 'Discounts are not allowed on cancelled or completed bookings.'];
        }

        $hasPayments = (float) $booking->total_paid > 0.009 || $booking->payments()->exists();

        // Simple policy: staff can discount only before any payments exist; admins can override.
        if ($hasPayments && (string) $actor->role !== 'admin') {
            return ['allowed' => false, 'reason' => 'has_payments', 'message' => 'Only admins can modify discounts after payments were recorded.'];
        }

        return ['allowed' => true, 'reason' => 'ok'];
    }

    public static function hasDiscount(Booking $booking): bool
    {
        return filled($booking->special_discount_type)
            && (float) ($booking->special_discount_amount_applied ?? 0) > 0.009
            && (float) ($booking->special_discount_original_total_price ?? 0) > 0.009;
    }

    public static function grossTotal(Booking $booking): float
    {
        if (self::hasDiscount($booking)) {
            return (float) $booking->special_discount_original_total_price;
        }

        return (float) $booking->total_price;
    }

    public static function discountAmount(Booking $booking): float
    {
        return (float) ($booking->special_discount_amount_applied ?? 0);
    }

    public static function netTotal(Booking $booking): float
    {
        return max(0, self::grossTotal($booking) - self::discountAmount($booking));
    }

    /**
     * @return array{gross: float, discount: float, net: float}
     */
    public static function preview(Booking $booking, string $type, float $value): array
    {
        $gross = max(0, self::grossTotal($booking));
        $discount = self::computeDiscountAmount($gross, $type, $value);
        $net = max(0, $gross - $discount);

        return compact('gross', 'discount', 'net');
    }

    public static function apply(
        Booking $booking,
        string $type,
        float $value,
        ?string $reasonCode,
        ?string $note,
        ?User $actor,
    ): Booking {
        $assessment = self::assessCanMutate($booking, $actor);
        if (! $assessment['allowed']) {
            throw new \InvalidArgumentException($assessment['message'] ?? 'Discount not allowed.');
        }

        $type = trim($type);
        if (! in_array($type, [self::TYPE_PERCENT, self::TYPE_FIXED], true)) {
            throw new \InvalidArgumentException('Invalid discount type.');
        }

        $value = (float) $value;
        if (! is_finite($value) || $value <= 0) {
            throw new \InvalidArgumentException('Discount value must be greater than 0.');
        }

        $reasonCode = $reasonCode !== null ? trim($reasonCode) : null;
        $note = $note !== null ? trim($note) : null;

        return DB::transaction(function () use ($booking, $type, $value, $reasonCode, $note, $actor) {
            /** @var Booking $fresh */
            $fresh = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            $gross = max(0, self::grossTotal($fresh));
            $discountAmount = self::computeDiscountAmount($gross, $type, $value);
            $net = max(0, $gross - $discountAmount);

            $now = now();
            $actorId = $actor?->id;
            $isFirstApply = ! filled($fresh->special_discounted_at);

            $fresh->forceFill([
                'special_discount_type' => $type,
                'special_discount_value' => round($value, 2),
                'special_discount_reason_code' => $reasonCode,
                'special_discount_note' => $note,
                'special_discount_original_total_price' => round($gross, 2),
                'special_discount_amount_applied' => round($discountAmount, 2),
                'total_price' => round($net, 2),
                'special_discounted_by_user_id' => $isFirstApply ? $actorId : ($fresh->special_discounted_by_user_id ?? $actorId),
                'special_discounted_at' => $isFirstApply ? $now : ($fresh->special_discounted_at ?? $now),
                'special_discount_last_modified_by_user_id' => $actorId,
                'special_discount_last_modified_at' => $now,
            ])->save();

            ActivityLogger::log(
                category: 'booking',
                event: $isFirstApply ? 'booking.discount_applied' : 'booking.discount_updated',
                description: sprintf(
                    '%s %s special discount on booking %s (gross ₱%s → net ₱%s; discount ₱%s; %s %s).',
                    (string) ($actor?->name ?? 'System'),
                    $isFirstApply ? 'applied a' : 'updated the',
                    (string) $fresh->reference_number,
                    number_format($gross, 2),
                    number_format($net, 2),
                    number_format($discountAmount, 2),
                    $type === self::TYPE_PERCENT ? rtrim(rtrim(number_format($value, 2), '0'), '.') : '₱'.number_format($value, 2),
                    $type === self::TYPE_PERCENT ? '%' : 'fixed',
                ),
                subject: $fresh,
                meta: [
                    'reference_number' => (string) $fresh->reference_number,
                    'discount' => [
                        'type' => $type,
                        'value' => round($value, 2),
                        'reason_code' => $reasonCode,
                        'note' => $note,
                        'gross_total' => round($gross, 2),
                        'discount_amount' => round($discountAmount, 2),
                        'net_total' => round($net, 2),
                    ],
                    'changed_by_user_id' => $actorId,
                    'changed_by_user_name' => (string) ($actor?->name ?? ''),
                ],
                userId: $actorId,
            );

            return $fresh->fresh();
        });
    }

    public static function remove(Booking $booking, ?User $actor): Booking
    {
        $assessment = self::assessCanMutate($booking, $actor);
        if (! $assessment['allowed']) {
            throw new \InvalidArgumentException($assessment['message'] ?? 'Discount not allowed.');
        }

        return DB::transaction(function () use ($booking, $actor) {
            /** @var Booking $fresh */
            $fresh = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            $gross = max(0, self::grossTotal($fresh));
            $oldDiscount = self::discountAmount($fresh);
            $net = $gross; // removing discount

            if (! self::hasDiscount($fresh)) {
                return $fresh;
            }

            $now = now();
            $actorId = $actor?->id;

            $fresh->forceFill([
                'total_price' => round($net, 2),
                'special_discount_type' => null,
                'special_discount_value' => null,
                'special_discount_reason_code' => null,
                'special_discount_note' => null,
                'special_discount_original_total_price' => null,
                'special_discount_amount_applied' => null,
                'special_discount_last_modified_by_user_id' => $actorId,
                'special_discount_last_modified_at' => $now,
            ])->save();

            ActivityLogger::log(
                category: 'booking',
                event: 'booking.discount_removed',
                description: sprintf(
                    '%s removed special discount on booking %s (net ₱%s → ₱%s; restored ₱%s).',
                    (string) ($actor?->name ?? 'System'),
                    (string) $fresh->reference_number,
                    number_format(max(0, $gross - $oldDiscount), 2),
                    number_format($net, 2),
                    number_format($oldDiscount, 2),
                ),
                subject: $fresh,
                meta: [
                    'reference_number' => (string) $fresh->reference_number,
                    'gross_total' => round($gross, 2),
                    'discount_removed' => round($oldDiscount, 2),
                    'net_total' => round($net, 2),
                    'changed_by_user_id' => $actorId,
                    'changed_by_user_name' => (string) ($actor?->name ?? ''),
                ],
                userId: $actorId,
            );

            return $fresh->fresh();
        });
    }

    private static function computeDiscountAmount(float $gross, string $type, float $value): float
    {
        $gross = max(0, (float) $gross);
        $value = (float) $value;

        $amount = match ($type) {
            self::TYPE_PERCENT => $gross * ($value / 100),
            self::TYPE_FIXED => $value,
            default => 0.0,
        };

        $amount = round($amount, 2);

        return min(max(0, $amount), $gross);
    }
}

