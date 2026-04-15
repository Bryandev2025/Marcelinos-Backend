<?php

namespace App\Notifications\Slack;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Synchronous delivery: queue workers may be down when the app is failing.
 */
class ServerErrorSlackNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Throwable $throwable,
        public ?string $requestUrl = null,
        public ?string $requestMethod = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $e = $this->throwable;
        $class = $e::class;
        $file = $e->getFile();
        $line = $e->getLine();
        $msg = $e->getMessage();
        $preview = Str::limit($msg, 500, '…');

        $fallback = 'Server error: '.$class.' · '.$file.':'.$line;

        return (new SlackMessage)
            ->username(config('app.name').' errors')
            ->emoji(':rotating_light:')
            ->text($fallback)
            ->headerBlock(':rotating_light: Server / API exception')
            ->sectionBlock(function ($block) use ($class, $preview, $file, $line): void {
                $block->field('*Exception*')->markdown();
                $block->field('`'.$class.'`')->markdown();
                $block->field('*Message*')->markdown();
                $block->field($preview !== '' ? $preview : '—')->markdown();
                $block->field('*Location*')->markdown();
                $block->field('`'.$file.':'.$line.'`')->markdown();
            })
            ->sectionBlock(function ($block): void {
                $method = $this->requestMethod;
                $url = $this->requestUrl;
                if (($method === null || $method === '') && ($url === null || $url === '')) {
                    $block->text('_No HTTP request context (CLI or early bootstrap)._')->markdown();

                    return;
                }
                $lines = [];
                if ($method !== null && $method !== '') {
                    $lines[] = '*Method* `'.$method.'`';
                }
                if ($url !== null && $url !== '') {
                    $lines[] = '*URL* '.Str::limit($url, 500, '…');
                }
                $block->text(implode("\n", $lines))->markdown();
            });
    }
}
