<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
            /** Optional: route error alerts to a different channel than booking alerts. */
            'error_channel' => env('SLACK_ERROR_ALERTS_CHANNEL'),
            /** Dedup window for repeated server exceptions (minutes). */
            'error_alert_throttle_minutes' => (int) env('SLACK_ERROR_ALERT_THROTTLE_MINUTES', 5),
            /** Dedup window for similar client error reports (minutes). */
            'client_error_throttle_minutes' => (int) env('SLACK_CLIENT_ERROR_THROTTLE_MINUTES', 2),
        ],
    ],

    'api' => [
        'key' => env('API_KEY'),
    ],

    'turnstile' => [
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    /*
     * Booking cancel/reschedule OTP is sent by email (see BookingActionOtpService).
     */
    'booking_action_otp' => [
        'max_sends_before_cooldown' => (int) env('BOOKING_ACTION_OTP_MAX_SENDS', 3),
        'cooldown_seconds' => (int) env('BOOKING_ACTION_OTP_COOLDOWN_SECONDS', 60),
    ],

    'semaphore' => [
        'api_key' => env('SEMAPHORE_API_KEY'),
        'otp_url' => env('SEMAPHORE_OTP_URL', 'https://api.semaphore.co/api/v4/otp'),
        'messages_url' => env('SEMAPHORE_MESSAGES_URL', 'https://api.semaphore.co/api/v4/messages'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME'),
    ],

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
        'invoice_url' => env('XENDIT_INVOICE_URL', 'https://api.xendit.co/v2/invoices'),
    ],

    'google_sheets' => [
        'enabled' => filter_var(env('GOOGLE_SHEETS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
        'credentials_path' => storage_path('app/google-credentials.json'),
            'status_to_sheet' => [
            'pending_verification' => 'Pending email',
            'reserved' => 'Reserved',
            'occupied' => 'Checked in',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'rescheduled' => 'Rescheduled',
    ],
    ],

];
