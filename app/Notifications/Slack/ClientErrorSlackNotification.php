<?php

namespace App\Notifications\Slack;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Str;

class ClientErrorSlackNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $message,
        public ?string $stack,
        public string $source,
        public ?string $pageUrl,
        public ?string $componentStack,
    ) {}

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $msg = Str::limit($this->message, 800, '…');
        $stack = $this->stack !== null && $this->stack !== ''
            ? Str::limit($this->stack, 2500, '…')
            : null;
        $comp = $this->componentStack !== null && $this->componentStack !== ''
            ? Str::limit($this->componentStack, 2500, '…')
            : null;

        $fallback = 'Client error ('.$this->source.'): '.$msg;

        $slack = (new SlackMessage)
            ->username(config('app.name').' client errors')
            ->emoji(':globe_with_meridians:')
            ->text($fallback)
            ->headerBlock(':globe_with_meridians: Front-end error report')
            ->sectionBlock(function ($block) use ($msg): void {
                $block->field('*Source*')->markdown();
                $block->field('`'.$this->source.'`')->markdown();
                $block->field('*Message*')->markdown();
                $block->field($msg)->markdown();
            });

        if ($this->pageUrl !== null && $this->pageUrl !== '') {
            $slack->sectionBlock(function ($block): void {
                $block->text('*Page* '.Str::limit($this->pageUrl, 800, '…'))->markdown();
            });
        }

        if ($stack !== null) {
            $slack->sectionBlock(function ($block) use ($stack): void {
                $block->text("```\n".$stack."\n```")->markdown();
            });
        }

        if ($comp !== null) {
            $slack->sectionBlock(function ($block) use ($comp): void {
                $block->text('*Component stack*'."\n```\n".$comp."\n```")->markdown();
            });
        }

        return $slack;
    }
}
