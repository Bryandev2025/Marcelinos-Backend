<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Review Notice</title>
</head>
<body style="margin:0; padding:24px; background:#f9fafb; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background:#ffffff; border:1px solid #e5e7eb; border-radius:10px;">
                    <tr>
                        <td style="padding:24px;">
                            <h2 style="margin:0 0 12px; font-size:22px;">Hello {{ $booking->guest?->full_name ?? 'Guest' }},</h2>
                            <p style="margin:0 0 12px; line-height:1.6;">
                                Your booking <strong>{{ $booking->reference_number }}</strong> was rescheduled and our team is reviewing
                                your payment records for a possible refund adjustment.
                            </p>
                            <p style="margin:0 0 12px; line-height:1.6;">
                                We will send another update once the review is completed.
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
