<?php

namespace App\Notifications\Slack;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Str;

class XenditWebhookFailureSlackNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $reason,
        public array $payload,
    ) {}

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $status = strtoupper((string) ($this->payload['status'] ?? ''));
        $invoiceId = (string) ($this->payload['id'] ?? '');
        $externalId = (string) ($this->payload['external_id'] ?? '');
        $paidAmount = $this->payload['paid_amount'] ?? null;
        $paidAt = (string) ($this->payload['paid_at'] ?? '');

        $title = 'Xendit webhook: '.$this->humanReason();

        $fallback = "{$title} · invoice {$invoiceId} · status {$status}";

        return (new SlackMessage)
            ->username(config('app.name').' alerts')
            ->emoji(':warning:')
            ->text($fallback)
            ->headerBlock($title)
            ->sectionBlock(function ($block) use ($status, $invoiceId, $externalId, $paidAmount, $paidAt): void {
                $block->field('*Reason code*')->markdown();
                $block->field('`'.$this->reason.'`')->markdown();
                $block->field('*Invoice ID*')->markdown();
                $block->field($invoiceId !== '' ? $invoiceId : '—')->markdown();
                $block->field('*External ID*')->markdown();
                $block->field($externalId !== '' ? $externalId : '—')->markdown();
                $block->field('*Payload status*')->markdown();
                $block->field($status !== '' ? $status : '—')->markdown();
                $block->field('*Paid amount (raw)*')->markdown();
                $block->field($paidAmount !== null && $paidAmount !== '' ? (string) $paidAmount : '—')->markdown();
                if ($paidAt !== '') {
                    $block->field('*Paid at*')->markdown();
                    $block->field($paidAt)->markdown();
                }
            })
            ->contextBlock(function ($block): void {
                $json = json_encode($this->redactedPayload(), JSON_UNESCAPED_SLASHES);
                $snippet = Str::limit((string) $json, 2800, '…');
                $block->text('Payload (redacted): '.$snippet);
            });
    }

    private function humanReason(): string
    {
        return match ($this->reason) {
            'invalid_callback_token' => 'Invalid callback token',
            'booking_not_found' => 'Booking not found',
            'unsupported_status' => 'Ignored status (non-paid)',
            default => $this->reason,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function redactedPayload(): array
    {
        $allow = ['id', 'external_id', 'status', 'paid_amount', 'paid_at', 'invoice_url', 'metadata', 'currency', 'merchant_name'];

        $out = [];
        foreach ($allow as $key) {
            if (array_key_exists($key, $this->payload)) {
                $out[$key] = $this->payload[$key];
            }
        }

        return $out;
    }
}
