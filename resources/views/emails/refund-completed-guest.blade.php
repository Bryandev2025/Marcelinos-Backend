<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Update</title>
</head>
<body style="margin:0; padding:24px; background:#f9fafb; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background:#ffffff; border:1px solid #e5e7eb; border-radius:10px;">
                    <tr>
                        <td style="padding:24px;">
                            @php
                                $totalPaid = (float) $booking->total_paid;
                                $totalPrice = (float) $booking->total_price;
                                $refundAmount = max(0, $totalPaid - $totalPrice);
                            @endphp
                            <h2 style="margin:0 0 12px; font-size:22px;">Hello {{ $booking->guest?->full_name ?? 'Guest' }},</h2>
                            <p style="margin:0 0 12px; line-height:1.6;">
                                Your booking <strong>{{ $booking->reference_number }}</strong> has been updated after rescheduling.
                            </p>
                            <p style="margin:0 0 12px; line-height:1.6;">
                                Refund amount: <strong>PHP {{ number_format($refundAmount, 2) }}</strong>.
                            </p>
                            <p style="margin:0 0 12px; line-height:1.6;">
                                If you need help with this refund update, reply to this email and our team will assist.
                            </p>
                            <p style="margin:0; color:#6b7280; font-size:13px;">
                                Marcelino's Resort Hotel
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
