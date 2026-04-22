<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Concerns\InteractsWithBookingOperations;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\Venue;
use App\Support\BookingFullBalancePayment;
use App\Support\BookingPricing;
use Filament\Actions\Action;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class EditBooking extends EditRecord
{
    use InteractsWithBookingOperations;

    protected static string $resource = BookingResource::class;

    protected bool $shouldRecordFullPaymentAfterSave = false;

    public function form(Schema $schema): Schema
    {
        $configured = BookingResource::form($schema);
        $components = array_values($configured->getComponents());

        // Keep the edit page compact; booking details move to a slide-over action.
        array_shift($components);

        return $configured->components([
            $this->makeBookingOperationsSectionForEdit(),
            ...array_values($components),
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

            if ($incomingPaymentStatus === Booking::PAYMENT_STATUS_PAID && ! $wasAlreadyPaid && $hasOutstandingBalance) {
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
        }

        return $data;
    }

    protected function afterSave(): void
    {
        parent::afterSave();

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
                $this->makeBookingDetailsSlideOverAction(),
                ViewAction::make(),
                RestoreAction::make(),
                TypedForceDeleteAction::make(fn (Booking $record): string => $record->reference_number),
            ];
        }

        return [
            $this->makeBookingDetailsSlideOverAction(),
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

    protected function makeBookingDetailsSlideOverAction(): Action
    {
        return Action::make('bookingDetails')
            ->label('Booking details')
            ->icon('heroicon-o-bars-3-bottom-left')
            ->color('gray')
            ->slideOver()
            ->modalHeading('Booking details')
            ->modalDescription('Edit guest, assigned rooms, and venue details without leaving the page.')
            ->modalSubmitActionLabel('Apply to form')
            ->fillForm(fn (): array => [
                'guest_id' => $this->data['guest_id'] ?? $this->record->guest_id,
                'rooms' => $this->data['rooms'] ?? $this->record->rooms->pluck('id')->all(),
                'venues' => $this->data['venues'] ?? $this->record->venues->pluck('id')->all(),
                'venue_event_type' => $this->data['venue_event_type'] ?? $this->record->venue_event_type,
            ])
            ->form([
                Select::make('guest_id')
                    ->label('Guest')
                    ->options(fn (): array => Guest::query()
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                        ->get()
                        ->mapWithKeys(fn (Guest $guest): array => [$guest->id => $guest->full_name])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required(),
                Placeholder::make('guest_booking_summary')
                    ->label('Guest booking summary')
                    ->content(function (): HtmlString {
                        $record = $this->record;
                        $record->loadMissing('roomLines');

                        if ($record->roomLines->isEmpty()) {
                            return new HtmlString('No room-line summary for this booking.');
                        }

                        $html = '<ul class="list-disc ms-5 space-y-1 text-sm">';
                        foreach ($record->roomLines as $line) {
                            $html .= '<li>'.e($line->displayLabel()).' × '.(int) $line->quantity.'</li>';
                        }
                        $html .= '</ul>';

                        return new HtmlString($html);
                    }),
                Select::make('rooms')
                    ->label('Assigned rooms')
                    ->options(function (): array {
                        $booking = $this->record;
                        $booking->loadMissing(['roomLines', 'rooms']);

                        $eligible = Room::idsEligibleForBookingAssignment($booking);

                        $query = Room::query()
                            ->where('status', '!=', Room::STATUS_MAINTENANCE)
                            ->with('bedSpecifications')
                            ->orderBy('type')
                            ->orderBy('name');

                        if (is_array($eligible)) {
                            if ($eligible === []) {
                                return [];
                            }

                            $query->whereIn('id', $eligible);
                        }

                        return $query
                            ->get()
                            ->mapWithKeys(fn (Room $room): array => [$room->id => $room->adminSelectLabel()])
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->native(false),
                Select::make('venues')
                    ->label('Venues')
                    ->options(fn (): array => Venue::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->native(false),
                Radio::make('venue_event_type')
                    ->label('Venue event type')
                    ->options(BookingPricing::venueEventTypeOptions()),
            ])
            ->action(function (array $data): void {
                $merged = array_merge($this->data, [
                    'guest_id' => $data['guest_id'] ?? null,
                    'rooms' => array_values(array_filter((array) ($data['rooms'] ?? []))),
                    'venues' => array_values(array_filter((array) ($data['venues'] ?? []))),
                    'venue_event_type' => ! empty(array_filter((array) ($data['venues'] ?? [])))
                        ? ($data['venue_event_type'] ?? null)
                        : null,
                ]);

                $merged = BookingForm::syncDerivedState($merged, $this->record);

                $this->form->fill($merged);
            });
    }
}
