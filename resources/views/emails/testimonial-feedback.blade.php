<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Share Your Experience</title>
</head>
<body style="margin:0; padding:0; background-color:#ffffff; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        We'd love your feedback. Share your experience: {{ $feedbackUrl }}
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
                                        <img src="{{ rtrim(config('app.url'), '/') }}/logo.webp" alt="Marcelino's" width="120" style="display:block; height:auto;" />
                                    </td>
                                    <td style="vertical-align:middle; text-align:right; color:#111827;">
                                        <div style="font-size:16px; font-weight:700;">We'd Love Your Feedback</div>
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
                                Thank you for staying with us. We hope you had a great experience and would love to hear from you.
                            </p>
                            <p style="margin:0 0 20px; color:#4b5563; font-size:14.5px;">
                                Your feedback helps us improve and helps other guests discover what we offer. It only takes a minute.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 24px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto;">
                                <tr>
                                    <td style="border-radius:8px; background-color:#111827;">
                                        <a href="{{ $feedbackUrl }}" target="_blank" rel="noopener noreferrer" style="display:inline-block; padding:14px 28px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                                            Share your experience
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:16px 0 0; font-size:12.5px; color:#6b7280;">
                                This link is unique to your stay and expires in 14 days.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 24px;">
                            <p style="margin:0; font-size:13.5px; color:#6b7280;">
                                If the button doesn’t work, copy and paste this link into your browser:<br>
                                <a href="{{ $feedbackUrl }}" style="color:#2563eb; word-break:break-all;">{{ $feedbackUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px; font-size:12.5px; color:#6b7280; border-top:1px solid #e5e7eb;">
                            <div style="font-weight:600; color:#374151;">Marcelino’s Team</div>
                            <div>Thank you for choosing us.</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
