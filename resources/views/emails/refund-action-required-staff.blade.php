<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Alert</title>
</head>
<body style="margin:0; padding:24px; background:#f9fafb; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    @php
        use App\Models\Booking;
        use App\Support\CancellationPolicy;

        $booking->loadMissing(['guest', 'payments']);
        $totalPaid = (float) $booking->total_paid;
        $totalPrice = (float) $booking->total_price;
        $overageRefund = max(0, $totalPaid - $totalPrice);
        $isCancelled = (string) $booking->booking_status === Booking::BOOKING_STATUS_CANCELLED;
        $cancellation = $isCancelled ? CancellationPolicy::breakdownForCancelledBooking($totalPrice, $totalPaid) : null;
        $appliesPercent = $cancellation !== null && ! empty($cancellation['applies_cancellation_percent']);
        $latestPayment = $booking->payments->sortByDesc('created_at')->first();
    @endphp
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="700" cellspacing="0" cellpadding="0" style="background:#ffffff; border:1px solid #e5e7eb; border-radius:10px;">
                    <tr>
                        <td style="padding:24px;">
                            <h2 style="margin:0 0 12px; font-size:22px;">Refund action required</h2>
                            <p style="margin:0 0 16px; line-height:1.6;">
                                Booking <strong>{{ $booking->reference_number }}</strong> is marked <strong>Refund pending</strong>. Process the guest refund externally, then mark the booking as refunded in admin when complete.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;">
                                <tr><td style="padding:6px 0; color:#6b7280;">Booking reference</td><td style="padding:6px 0;"><strong>{{ $booking->reference_number }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Guest</td><td style="padding:6px 0;"><strong>{{ $booking->guest?->full_name ?? 'N/A' }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Guest email</td><td style="padding:6px 0;"><strong>{{ $booking->guest?->email ?? 'N/A' }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Total price</td><td style="padding:6px 0;"><strong>PHP {{ number_format($totalPrice, 2) }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Total paid</td><td style="padding:6px 0;"><strong>PHP {{ number_format($totalPaid, 2) }}</strong></td></tr>
                                @if($cancellation !== null && $appliesPercent)
                                <tr><td style="padding:6px 0; color:#6b7280;">Cancellation deduction</td><td style="padding:6px 0;"><strong>{{ (int) $cancellation['fee_percent'] }}% of booking total (PHP {{ number_format($cancellation['fee_from_total'], 2) }})</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Amount retained (fee)</td><td style="padding:6px 0;"><strong>PHP {{ number_format($cancellation['amount_to_keep'], 2) }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Amount to refund (policy)</td><td style="padding:6px 0;"><strong>PHP {{ number_format($cancellation['amount_to_refund'], 2) }}</strong></td></tr>
                                @elseif($cancellation !== null)
                                <tr><td style="padding:6px 0; color:#6b7280;" colspan="2">Partial payment at cancellation: treated as a <strong>non-refundable reservation fee</strong>. No guest refund; retain PHP <strong>{{ number_format($cancellation['amount_to_keep'], 2) }}</strong>.</td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Amount to refund</td><td style="padding:6px 0;"><strong>PHP {{ number_format($cancellation['amount_to_refund'], 2) }}</strong></td></tr>
                                @else
                                <tr><td style="padding:6px 0; color:#6b7280;">Refund amount (paid minus new total)</td><td style="padding:6px 0;"><strong>PHP {{ number_format($overageRefund, 2) }}</strong></td></tr>
                                @endif
                                <tr><td style="padding:6px 0; color:#6b7280;">Payment method</td><td style="padding:6px 0;"><strong>{{ $booking->payment_method ?: 'N/A' }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Provider ref</td><td style="padding:6px 0;"><strong>{{ $latestPayment?->provider_ref ?? 'N/A' }}</strong></td></tr>
                            </table>

                            <p style="margin:18px 0 0;">
                                <a href="{{ $bookingAdminUrl }}" style="color:#2563eb; text-decoration:none; font-weight:600;">Open booking in admin</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
