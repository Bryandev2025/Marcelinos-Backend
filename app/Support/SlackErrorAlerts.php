<?php

namespace App\Support;

use App\Notifications\Slack\ClientErrorSlackNotification;
use App\Notifications\Slack\ServerErrorSlackNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class SlackErrorAlerts
{
    /**
     * Separate from booking alerts so ops can enable error Slack without booking noise (or vice versa).
     */
    public static function enabled(): bool
    {
        if (! filter_var(env('SLACK_ERROR_ALERTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $token = trim((string) Config::get('services.slack.notifications.bot_user_oauth_token', ''));
        $channel = trim((string) self::channel());

        return $token !== '' && $channel !== '';
    }

    public static function channel(): string
    {
        $override = trim((string) Config::get('services.slack.notifications.error_channel', ''));

        return $override !== ''
            ? $override
            : (string) Config::get('services.slack.notifications.channel', '');
    }

    public static function notify(Notification $notification): void
    {
        if (! self::enabled()) {
            return;
        }

        NotificationFacade::route('slack', self::channel())->notify($notification);
    }

    /**
     * Called from the global exception report pipeline for server-side errors.
     */
    public static function notifyIfReportable(Throwable $e): void
    {
        if (! self::enabled()) {
            return;
        }

        if (! self::shouldNotifyForThrowable($e)) {
            return;
        }

        $fingerprint = 'slack:server_error:'.sha1(
            $e::class.'|'.$e->getFile().'|'.$e->getLine().'|'.substr($e->getMessage(), 0, 240)
        );

        if (Cache::has($fingerprint)) {
            return;
        }

        Cache::put($fingerprint, true, now()->addMinutes((int) Config::get('services.slack.notifications.error_alert_throttle_minutes', 5)));

        try {
            $request = function_exists('request') ? request() : null;
            $url = $request?->fullUrl();
            $method = $request?->method();

            self::notify(new ServerErrorSlackNotification($e, $url, $method));
        } catch (Throwable) {
            // Never break Laravel's reporting pipeline.
        }
    }

    /**
     * @param  array{message: string, stack?: string|null, source?: string|null, page_url?: string|null, component_stack?: string|null}  $payload
     */
    public static function notifyClientPayload(array $payload): void
    {
        if (! self::enabled()) {
            return;
        }

        $message = (string) ($payload['message'] ?? '');
        $pageUrl = (string) ($payload['page_url'] ?? '');
        $source = (string) ($payload['source'] ?? 'client');

        $fingerprint = 'slack:client_error:'.sha1($source.'|'.$pageUrl.'|'.substr($message, 0, 400));

        if (Cache::has($fingerprint)) {
            return;
        }

        Cache::put($fingerprint, true, now()->addMinutes((int) Config::get('services.slack.notifications.client_error_throttle_minutes', 2)));

        try {
            self::notify(new ClientErrorSlackNotification(
                $message,
                $payload['stack'] ?? null,
                $source,
                $pageUrl !== '' ? $pageUrl : null,
                $payload['component_stack'] ?? null,
            ));
        } catch (Throwable) {
            //
        }
    }

    public static function shouldNotifyForThrowable(Throwable $e): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        if ($e instanceof ValidationException) {
            return false;
        }

        if ($e instanceof AuthenticationException) {
            return false;
        }

        if ($e instanceof ModelNotFoundException) {
            return false;
        }

        if ($e instanceof AuthorizationException) {
            return false;
        }

        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode() >= 500;
        }

        return true;
    }
}
