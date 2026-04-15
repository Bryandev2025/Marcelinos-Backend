<?php

namespace App\Notifications\Slack;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\BookingRoomLine;
use App\Models\Payment;
use App\Models\Room;
use App\Support\BookingPricing;
use Carbon\Carbon;
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
        $this->booking->loadMissing(['guest', 'rooms', 'venues', 'roomLines']);

        $booking = $this->booking;
        $eventIcon = $this->eventIcon();
        $header = $eventIcon.' '.$this->headerText();
        $guestName = trim((string) ($booking->guest?->full_name ?? '')) ?: '—';
        $checkIn = $this->formatBookingDateTimeManila($booking->check_in);
        $checkOut = $this->formatBookingDateTimeManila($booking->check_out);
        $total = number_format((float) $booking->total_price, 2).' PHP';

        $paymentLine = '';
        if (in_array($this->event, [Booking::STATUS_PAID, Booking::STATUS_PARTIAL], true)) {
            /** @var Payment|null $latest */
            $latest = $booking->payments()->latest()->first();
            if ($latest) {
                $paid = number_format((float) $latest->partial_amount, 2);
                $target = number_format((float) $latest->total_amount, 2);
                $provIcon = $this->paymentProviderStatusIcon($latest->provider_status);
                $paymentLine = "Provider: {$latest->provider} · Ref: {$latest->provider_ref} · Paid: {$paid} / {$target} PHP · Status: {$provIcon} {$latest->provider_status} · Fully paid: ".($latest->is_fullypaid ? '✅ yes' : '⏳ no');
            }
        }

        $roomsRequested = $this->formatRoomLinesForSlack($booking);
        $venuesDetail = $this->formatVenuesForSlack($booking);
        $venueEventDetail = $this->formatVenueEventTypeForSlack($booking);
        $assignedRooms = $this->formatAssignedRoomsForSlack($booking);

        $adminUrl = $this->adminBookingUrl();

        $fallback = "{$header} · {$booking->reference_number} · {$guestName}";

        $statusLabel = Booking::statusOptions()[$booking->status] ?? ucfirst((string) $booking->status);
        $statusCell = $this->bookingStatusIcon($booking->status).' '.$statusLabel;

        $plan = filled($booking->online_payment_plan) ? $booking->online_payment_plan : '—';
        $xendit = filled($booking->xendit_invoice_id) ? $booking->xendit_invoice_id : '—';

        $message = (new SlackMessage)
            ->username(config('app.name').' bookings')
            ->emoji($eventIcon)
            ->text($fallback)
            ->headerBlock($header)
            ->usingBlockKitTemplate(json_encode([
                'blocks' => [
                    $this->bookingDetailsTableBlock(
                        (string) $booking->reference_number,
                        $guestName,
                        $checkIn,
                        $checkOut,
                        $statusCell,
                        $total,
                        $booking->payment_method ?: '—',
                        $plan,
                        $xendit,
                        $roomsRequested,
                        $venuesDetail,
                        $venueEventDetail,
                        $assignedRooms,
                    ),
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        if ($paymentLine !== '') {
            $message->dividerBlock()->sectionBlock(function ($block) use ($paymentLine): void {
                $block->text("*💳 Latest payment*\n{$paymentLine}")->markdown();
            });
        }

        return $message
            ->contextBlock(function ($block) use ($booking): void {
                $block->text('Receipt token: '.substr((string) $booking->receipt_token, 0, 8).'… ID: '.$booking->id);
            })
            ->when($adminUrl !== '', fn (SlackMessage $msg) => $msg->actionsBlock(function ($block) use ($adminUrl): void {
                $block->button('Open in admin')->url($adminUrl)->primary();
            }));
    }

    /**
     * @return array{type: string, column_settings?: list<array<string, mixed>>, rows: list<list<array<string, mixed>>>}
     */
    private function bookingDetailsTableBlock(
        string $referenceNumber,
        string $guestName,
        string $checkIn,
        string $checkOut,
        string $statusCell,
        string $total,
        string $paymentMethod,
        string $plan,
        string $xendit,
        string $roomsRequested,
        string $venuesDetail,
        string $venueEventDetail,
        string $assignedRooms,
    ): array {
        $L = fn (string $text) => $this->slackTableRichTextCell($text, bold: true);
        $V = fn (string $text) => $this->slackTableRichTextCell($text);

        $rows = [
            [$L('Field'), $L('Value')],
            [$L('🔖 Reference'), $this->slackTableRichTextCell($referenceNumber, code: true)],
            [$L('👤 Guest'), $V($guestName)],
            [$L('🛬 Check-in (PH Time)'), $V($checkIn)],
            [$L('🛫 Check-out (PH Time)'), $V($checkOut)],
            [$L('📌 Status'), $V($statusCell)],
            [$L('💰 Total'), $V($total)],
            [$L('💳 Payment method'), $V($paymentMethod)],
            [$L('🌐 Online payment plan'), $V($plan)],
            [$L('📄 Xendit invoice'), $V($xendit)],
            [$L('🛏️ Rooms requested'), $V($roomsRequested)],
            [$L('🏢 Venues'), $V($venuesDetail)],
        ];

        if ($venueEventDetail !== '—') {
            $rows[] = [$L('🎪 Venue event type'), $V($venueEventDetail)];
        }

        if ($assignedRooms !== '—') {
            $rows[] = [$L('🔑 Assigned rooms'), $V($assignedRooms)];
        }

        return [
            'type' => 'table',
            'column_settings' => [
                ['is_wrapped' => true],
                ['is_wrapped' => true],
            ],
            'rows' => $rows,
        ];
    }

    private function formatBookingDateTimeManila(?Carbon $dateTime): string
    {
        if ($dateTime === null) {
            return '—';
        }

        return $dateTime->timezone(Booking::timezoneManila())->format('F j, Y g:i A');
    }

    private function formatRoomLinesForSlack(Booking $booking): string
    {
        if ($booking->roomLines->isEmpty()) {
            return '—';
        }

        return $booking->roomLines
            ->map(function (BookingRoomLine $line): string {
                $label = $line->displayLabel();
                $q = max(1, (int) $line->quantity);

                return $q > 1 ? "{$q}× {$label}" : $label;
            })
            ->implode("\n");
    }

    private function formatVenuesForSlack(Booking $booking): string
    {
        if ($booking->venues->isEmpty()) {
            return '—';
        }

        return $booking->venues
            ->pluck('name')
            ->map(fn (mixed $name) => trim((string) $name))
            ->filter()
            ->implode(', ');
    }

    private function formatVenueEventTypeForSlack(Booking $booking): string
    {
        $raw = $booking->venue_event_type;
        if (! filled($raw)) {
            return '—';
        }

        $options = BookingPricing::venueEventTypeOptions();
        $normalized = BookingPricing::normalizeVenueEventType($raw);

        return $options[$normalized] ?? ucfirst((string) $raw);
    }

    private function formatAssignedRoomsForSlack(Booking $booking): string
    {
        if ($booking->rooms->isEmpty()) {
            return '—';
        }

        $booking->rooms->loadMissing('bedSpecifications');

        return $booking->rooms
            ->map(fn (Room $room) => $room->adminSelectLabel())
            ->implode("\n");
    }

    /**
     * @return array{type: string, elements: list<array<string, mixed>>}
     */
    private function slackTableRichTextCell(string $text, bool $bold = false, bool $code = false): array
    {
        $style = [];
        if ($bold) {
            $style['bold'] = true;
        }
        if ($code) {
            $style['code'] = true;
        }

        $textElement = [
            'type' => 'text',
            'text' => $text,
        ];
        if ($style !== []) {
            $textElement['style'] = $style;
        }

        return [
            'type' => 'rich_text',
            'elements' => [
                [
                    'type' => 'rich_text_section',
                    'elements' => [$textElement],
                ],
            ],
        ];
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

    private function eventIcon(): string
    {
        return match ($this->event) {
            'created' => '✨',
            Booking::STATUS_CANCELLED => '🚫',
            Booking::STATUS_RESCHEDULED => '🔄',
            Booking::STATUS_COMPLETED => '🎉',
            Booking::STATUS_PAID => '✅',
            Booking::STATUS_PARTIAL => '🔶',
            default => '📣',
        };
    }

    private function bookingStatusIcon(string $status): string
    {
        return match ($status) {
            Booking::STATUS_UNPAID => '⏳',
            Booking::STATUS_PARTIAL => '🔶',
            Booking::STATUS_OCCUPIED => '🛏️',
            Booking::STATUS_PAID => '✅',
            Booking::STATUS_COMPLETED => '🏁',
            Booking::STATUS_CANCELLED => '❌',
            Booking::STATUS_RESCHEDULED => '🔄',
            default => '❔',
        };
    }

    private function paymentProviderStatusIcon(?string $status): string
    {
        $s = strtolower((string) $status);

        return match (true) {
            $s === '' => '🏷️',
            str_contains($s, 'paid') || str_contains($s, 'succeed') || str_contains($s, 'success') || $s === 'completed' || $s === 'settled' => '✅',
            str_contains($s, 'pend') || str_contains($s, 'await') || str_contains($s, 'process') => '⏳',
            str_contains($s, 'fail') || str_contains($s, 'cancel') || str_contains($s, 'expir') || str_contains($s, 'void') => '❌',
            str_contains($s, 'partial') => '🔶',
            default => '🏷️',
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
