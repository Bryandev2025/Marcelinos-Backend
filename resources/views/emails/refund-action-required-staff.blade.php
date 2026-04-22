<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Alert</title>
</head>
<body style="margin:0; padding:24px; background:#f9fafb; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    @php
        $booking->loadMissing(['guest', 'payments']);
        $totalPaid = (float) $booking->total_paid;
        $totalPrice = (float) $booking->total_price;
        $refundAmount = max(0, $totalPaid - $totalPrice);
        $latestPayment = $booking->payments->sortByDesc('created_at')->first();
    @endphp
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="700" cellspacing="0" cellpadding="0" style="background:#ffffff; border:1px solid #e5e7eb; border-radius:10px;">
                    <tr>
                        <td style="padding:24px;">
                            <h2 style="margin:0 0 12px; font-size:22px;">Reschedule Refund Alert</h2>
                            <p style="margin:0 0 16px; line-height:1.6;">
                                Booking <strong>{{ $booking->reference_number }}</strong> is now marked as refunded after reschedule.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;">
                                <tr><td style="padding:6px 0; color:#6b7280;">Booking reference</td><td style="padding:6px 0;"><strong>{{ $booking->reference_number }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Guest</td><td style="padding:6px 0;"><strong>{{ $booking->guest?->full_name ?? 'N/A' }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Guest email</td><td style="padding:6px 0;"><strong>{{ $booking->guest?->email ?? 'N/A' }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Total price</td><td style="padding:6px 0;"><strong>PHP {{ number_format($totalPrice, 2) }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Total paid</td><td style="padding:6px 0;"><strong>PHP {{ number_format($totalPaid, 2) }}</strong></td></tr>
                                <tr><td style="padding:6px 0; color:#6b7280;">Refund amount</td><td style="padding:6px 0;"><strong>PHP {{ number_format($refundAmount, 2) }}</strong></td></tr>
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
