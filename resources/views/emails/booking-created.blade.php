<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marcelino's Resort and Hotel</title>
</head>
<body style="margin:0; padding:0; background-color:#ffffff; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Your booking has been received. Reference: {{ $booking->reference_number }}
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#ffffff; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:22px 32px; border-bottom:1px solid #e5e7eb;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <img src="{{ asset('storage/logos/logo.png') }}" alt="Marcelino's" width="120" style="display:block; height:auto;" />
                                    </td>
                                    <td style="vertical-align:middle; text-align:right; color:#111827;">
                                        <div style="font-size:16px; font-weight:700;">Booking Confirmation</div>
                                        <div style="font-size:12.5px; color:#6b7280;">Reference {{ $booking->reference_number }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <p style="margin:0 0 12px; font-size:16px;">Hi {{ $booking->guest?->full_name ?? 'Guest' }},</p>
                            <p style="margin:0 0 16px; color:#4b5563; font-size:14.5px;">
                                Thanks for booking with us! Your reservation is now in our system. We'll follow up if we need anything else.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 16px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb; border-radius:10px;">
                                <tr>
                                    <td style="padding:14px 18px; background-color:#f9fafb; border-bottom:1px solid #e5e7eb;">
                                        <strong style="font-size:14px; color:#111827;">Booking Details</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 18px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px; color:#374151;">
                                            <tr>
                                                <td style="padding:6px 0; width:38%; color:#6b7280;">Reference</td>
                                                <td style="padding:6px 0; font-weight:600;">{{ $booking->reference_number }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280;">Check-in</td>
                                                <td style="padding:6px 0; font-weight:600;">{{ optional($booking->check_in)->format('M d, Y h:i A') }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280;">Check-out</td>
                                                <td style="padding:6px 0; font-weight:600;">{{ optional($booking->check_out)->format('M d, Y h:i A') }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280;">Status</td>
                                                <td style="padding:6px 0; font-weight:600; text-transform:capitalize;">{{ $booking->status }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#6b7280;">Total</td>
                                                <td style="padding:6px 0; font-weight:600;">{{ number_format($booking->total_price, 2) }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 24px;">
                            <p style="margin:0 0 12px; font-size:13.5px; color:#6b7280;">
                                <a href="{{ rtrim(config('app.frontend_url'), '/') }}/booking-receipt/{{ $booking->reference_number }}" style="color:#2563eb; font-weight:600; text-decoration:none;">View your booking →</a>
                            </p>
                            <p style="margin:0; font-size:13.5px; color:#6b7280;">
                                Need help? Just reply to this email and we'll assist you.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px; font-size:12.5px; color:#6b7280; border-top:1px solid #e5e7eb;">
                            <div style="font-weight:600; color:#374151;">Marcelino's Team</div>
                            <div>Thank you for choosing us.</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
