<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class SlackBookingAlerts
{
    public static function enabled(): bool
    {
        if (! filter_var(env('SLACK_BOOKING_ALERTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $token = trim((string) Config::get('services.slack.notifications.bot_user_oauth_token', ''));
        $channel = trim((string) Config::get('services.slack.notifications.channel', ''));

        return $token !== '' && $channel !== '';
    }

    public static function notify(Notification $notification): void
    {
        if (! self::enabled()) {
            return;
        }

        $channel = (string) Config::get('services.slack.notifications.channel');
        NotificationFacade::route('slack', $channel)->notify($notification);
    }

    public static function nonpaidXenditWebhookAlertsEnabled(): bool
    {
        return filter_var(env('SLACK_XENDIT_NOTIFY_NONPAID_EVENTS', false), FILTER_VALIDATE_BOOLEAN);
    }
}
