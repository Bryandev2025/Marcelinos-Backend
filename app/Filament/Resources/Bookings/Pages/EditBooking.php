<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Concerns\InteractsWithBookingOperations;
use App\Models\Booking;
use App\Support\BookingFullBalancePayment;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class EditBooking extends EditRecord
{
    use InteractsWithBookingOperations;

    protected static string $resource = BookingResource::class;

    protected bool $shouldRecordFullPaymentAfterSave = false;

    public function form(Schema $schema): Schema
    {
        $configured = BookingResource::form($schema);

        return $configured->components([
            $this->makeBookingOperationsSectionForEdit(),
            ...array_values($configured->getComponents()),
        ]);
    }

    public function getHeading(): string
    {
        return 'Edit booking';
    }

    public function getSubheading(): ?string
    {
        if (! $this->record instanceof Booking) {
            return null;
        }

        $guestName = $this->record->guest?->full_name ?: 'Unknown guest';

        return "{$this->record->reference_number} - {$guestName}";
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->shouldRecordFullPaymentAfterSave = false;

        $venues = $data['venues'] ?? [];
        if (! is_array($venues) || empty(array_filter($venues))) {
            $data['venue_event_type'] = null;
        }

        $record = $this->record;
        if ($record instanceof Booking) {
            $nextBookingStatus = (string) ($data['booking_status'] ?? $record->booking_status);
            $rooms = is_array($data['rooms'] ?? null) ? $data['rooms'] : [];
            $requiresAssignedRooms = in_array($nextBookingStatus, [
                Booking::BOOKING_STATUS_OCCUPIED,
                Booking::BOOKING_STATUS_COMPLETED,
            ], true);

            // Allow status/payment updates on frontend-created bookings that do not
            // yet have physical room assignment. Enforce assignment once operation
            // moves to occupied/completed.
            if ($requiresAssignedRooms || $rooms !== []) {
                Booking::validateAssignedRoomsFulfillRoomLines($record, $rooms);
            }

            $incomingPaymentStatus = (string) ($data['payment_status'] ?? $record->payment_status);
            $wasAlreadyPaid = (string) $record->payment_status === Booking::PAYMENT_STATUS_PAID;
            $hasOutstandingBalance = (float) $record->balance > 0.009;

            $canAutoRecordFullPayment = ! in_array($nextBookingStatus, [
                Booking::BOOKING_STATUS_CANCELLED,
                Booking::BOOKING_STATUS_COMPLETED,
            ], true);

            if ($incomingPaymentStatus === Booking::PAYMENT_STATUS_PAID && ! $wasAlreadyPaid && $hasOutstandingBalance && $canAutoRecordFullPayment) {
                $assessment = BookingFullBalancePayment::assess($record);

                if (! $assessment['allowed']) {
                    throw ValidationException::withMessages([
                        'payment_status' => [$assessment['message'] ?? 'This booking cannot be marked as paid yet.'],
                    ]);
                }

                // Let the payment recorder create the payment row + final paid status after save.
                $this->shouldRecordFullPaymentAfterSave = true;
                unset($data['payment_status']);
            }

            // If staff changed dates/rooms/venues, total_price may change. When the payment status
            // field is left untouched, keep it consistent with the actual paid amount.
            $refundPipelineStatuses = [
                Booking::PAYMENT_STATUS_REFUND_PENDING,
                Booking::PAYMENT_STATUS_NON_REFUNDABLE,
                Booking::PAYMENT_STATUS_REFUNDED,
            ];
            if (array_key_exists('payment_status', $data)
                && (string) $incomingPaymentStatus === (string) $record->payment_status
                && ! in_array($incomingPaymentStatus, $refundPipelineStatuses, true)
                && ! in_array((string) $record->payment_status, $refundPipelineStatuses, true)) {
                $newTotal = array_key_exists('total_price', $data) ? (float) $data['total_price'] : (float) $record->total_price;
                $paid = (float) $record->total_paid;
                $computed = Booking::paymentStatusFromAmounts($newTotal, $paid);
                if ($computed !== $incomingPaymentStatus) {
                    $data['payment_status'] = $computed;
                }
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->shouldRecordFullPaymentAfterSave || ! $this->record instanceof Booking) {
            return;
        }

        try {
            BookingFullBalancePayment::record($this->record);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title('Booking saved, but payment was not recorded')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->record->refresh();

        Notification::make()
            ->title('Full payment recorded')
            ->body('The remaining balance was added to Payments and the booking is now marked as paid.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        if ($this->record->trashed()) {
            return [
                ViewAction::make(),
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (Booking $record): string => $record->reference_number),
            ];
        }

        return [
            ViewAction::make(),
            TypedDeleteAction::make(fn (Booking $record): string => $record->reference_number),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        $record = $this->getRecord();
        if ($record instanceof Booking) {
            $record->refresh();

            return BookingResource::calendarUrlForBooking($record);
        }

        return parent::getRedirectUrl();
    }
}
