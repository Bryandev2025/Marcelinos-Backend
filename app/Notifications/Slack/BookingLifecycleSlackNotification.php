<?php

namespace App\Notifications\Slack;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Queue\SerializesModels;

class BookingLifecycleSlackNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $this->booking->loadMissing(['guest', 'rooms', 'venues']);

        $booking = $this->booking;
        $header = $this->headerText();
        $guestName = trim((string) ($booking->guest?->full_name ?? '')) ?: '—';
        $checkIn = $booking->check_in?->timezone(Booking::timezoneManila())->format('Y-m-d H:i') ?? '—';
        $checkOut = $booking->check_out?->timezone(Booking::timezoneManila())->format('Y-m-d H:i') ?? '—';
        $total = number_format((float) $booking->total_price, 2).' PHP';

        $paymentLine = '';
        if (in_array($this->event, [Booking::STATUS_PAID, Booking::STATUS_PARTIAL], true)) {
            /** @var Payment|null $latest */
            $latest = $booking->payments()->latest()->first();
            if ($latest) {
                $paid = number_format((float) $latest->partial_amount, 2);
                $target = number_format((float) $latest->total_amount, 2);
                $paymentLine = "Provider: {$latest->provider} · Ref: {$latest->provider_ref} · Paid: {$paid} / {$target} PHP · Status: {$latest->provider_status} · Fully paid: ".($latest->is_fullypaid ? 'yes' : 'no');
            }
        }

        $roomsCount = $booking->rooms->count();
        $venuesCount = $booking->venues->count();
        $components = "Rooms: {$roomsCount} · Venues: {$venuesCount}";

        $adminUrl = $this->adminBookingUrl();

        $fallback = "{$header} · {$booking->reference_number} · {$guestName}";

        $lines = [
            '*Reference:* '.$booking->reference_number,
            '*Guest:* '.$guestName,
            '*Check-in (Manila):* '.$checkIn,
            '*Check-out (Manila):* '.$checkOut,
            '*Status:* '.$booking->status,
            '*Total:* '.$total,
            '*Payment method:* '.($booking->payment_method ?: '—'),
        ];
        if (filled($booking->online_payment_plan)) {
            $lines[] = '*Online payment plan:* '.$booking->online_payment_plan;
        }
        if (filled($booking->xendit_invoice_id)) {
            $lines[] = '*Xendit invoice:* '.$booking->xendit_invoice_id;
        }
        if ($paymentLine !== '') {
            $lines[] = '*Latest payment:* '.$paymentLine;
        }
        $lines[] = '*Components:* '.$components;

        $body = implode("\n", $lines);

        return (new SlackMessage)
            ->username(config('app.name').' bookings')
            ->text($fallback)
            ->headerBlock($header)
            ->sectionBlock(function ($block) use ($body): void {
                $block->text($body)->markdown();
            })
            ->contextBlock(function ($block) use ($booking): void {
                $block->text('Receipt token: '.substr((string) $booking->receipt_token, 0, 8).'… ID: '.$booking->id);
            })
            ->when($adminUrl !== '', fn (SlackMessage $msg) => $msg->actionsBlock(function ($block) use ($adminUrl): void {
                $block->button('Open in admin')->url($adminUrl)->primary();
            }));
    }

    private function headerText(): string
    {
        return match ($this->event) {
            'created' => 'New booking',
            Booking::STATUS_CANCELLED => 'Booking cancelled',
            Booking::STATUS_RESCHEDULED => 'Booking rescheduled',
            Booking::STATUS_COMPLETED => 'Booking completed',
            Booking::STATUS_PAID => 'Payment received (paid)',
            Booking::STATUS_PARTIAL => 'Payment received (partial)',
            default => 'Booking update ('.$this->event.')',
        };
    }

    private function adminBookingUrl(): string
    {
        try {
            return BookingResource::getUrl('view', ['record' => $this->booking]);
        } catch (\Throwable) {
            return '';
        }
    }
}
