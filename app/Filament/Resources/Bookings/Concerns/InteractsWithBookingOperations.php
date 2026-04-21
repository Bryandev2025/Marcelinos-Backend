<?php

namespace App\Filament\Resources\Bookings\Concerns;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Support\BookingAdminGuidance;
use App\Support\BookingCheckInEligibility;
use App\Support\BookingFullBalancePayment;
use App\Support\BookingLifecycleActions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\HtmlString;

trait InteractsWithBookingOperations
{
    protected function makeBookingOperationsSectionForEdit(): Section
    {
        return Section::make(__('Front desk & payments'))
            ->description(__('Use the Payments tab to record partial cash payments. Use Settle remaining balance when the guest pays the full remainder in one step.'))
            ->visible(fn (): bool => $this->getRecord() instanceof Booking && ! $this->getRecord()->trashed())
            ->schema([
                Text::make('')
                    ->content(fn (): HtmlString => BookingAdminGuidance::operationsSummaryHtml($this->getRecord()))
                    ->columnSpanFull(),
                Actions::make([
                    Action::make('bookingOpPayBalance')
                        ->label(__('Settle remaining balance'))
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading(__('Mark booking as fully paid?'))
                        ->modalDescription(__('Records one payment for the full remaining balance and sets payment to Paid. For partial cash amounts, use Payments instead.'))
                        ->modalSubmitActionLabel(__('Yes, mark as paid'))
                        ->successNotificationTitle(__('Remaining balance recorded. Booking is now paid.'))
                        ->visible(fn (): bool => $this->shouldShowPayBalanceForRecord())
                        ->disabled(fn (): bool => $this->shouldShowPayBalanceForRecord() && ! BookingFullBalancePayment::assess($this->getRecord())['allowed'])
                        ->tooltip(fn (): ?string => $this->payBalanceBlockedTooltipForRecord())
                        ->action(function (): void {
                            $this->runBookingPayBalance();
                        }),
                    Action::make('bookingOpCheckIn')
                        ->label(__('Check in guest'))
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading(__('Check in this guest?'))
                        ->modalDescription(__('Sets stay status to Occupied (guest is on site).'))
                        ->visible(fn (): bool => $this->record instanceof Booking && BookingCheckInEligibility::assess($this->record)['allowed'])
                        ->action(function (): void {
                            $this->runBookingCheckIn();
                        }),
                    Action::make('bookingOpComplete')
                        ->label(__('Mark stay complete'))
                        ->icon('heroicon-o-flag')
                        ->color('secondary')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => $this->record instanceof Booking
                            && ! $this->record->trashed()
                            && $this->record->booking_status === Booking::BOOKING_STATUS_OCCUPIED
                            && $this->record->isCheckOutTodayManila())
                        ->action(function (): void {
                            $this->runBookingComplete();
                        }),
                    Action::make('bookingOpCancel')
                        ->label(__('Cancel booking'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('Cancel this booking?'))
                        ->visible(fn (): bool => $this->record instanceof Booking
                            && ! $this->record->trashed()
                            && ! in_array($this->record->booking_status, [Booking::BOOKING_STATUS_CANCELLED, Booking::BOOKING_STATUS_COMPLETED], true))
                        ->action(function (): void {
                            $this->runBookingCancel();
                        }),
                ])->columnSpanFull(),
            ])
            ->columns(1)
            ->collapsible()
            ->persistCollapsed()
            ->id('booking-operations-panel-edit')
            ->columnSpanFull();
    }

    protected function makeBookingOperationsSectionForView(): Section
    {
        return Section::make(__('Front desk & payments'))
            ->description(__('Actions are in the buttons above. Payments tab records partial cash; Settle remaining balance is in the header.'))
            ->visible(fn (): bool => $this->getRecord() instanceof Booking && ! $this->getRecord()->trashed())
            ->schema([
                Text::make('')
                    ->content(fn (): HtmlString => BookingAdminGuidance::operationsSummaryHtml($this->getRecord()))
                    ->columnSpanFull(),
            ])
            ->columns(1)
            ->collapsible()
            ->persistCollapsed()
            ->id('booking-operations-panel-view')
            ->columnSpanFull();
    }

    /**
     * Header actions for View booking (form is read-only; actions cannot live inside disabled form).
     *
     * @return array<Action>
     */
    protected function bookingLifecycleHeaderActionsForView(): array
    {
        return [
            $this->makePayBalanceHeaderAction(),
            Action::make('viewBookingCheckIn')
                ->label(__('Check in guest'))
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Check in this guest?'))
                ->modalDescription(__('Sets status to Occupied (guest is on site).'))
                ->visible(fn (): bool => $this->record instanceof Booking && BookingCheckInEligibility::assess($this->record)['allowed'])
                ->action(function (): void {
                    $this->runBookingCheckIn();
                }),
            Action::make('viewBookingComplete')
                ->label(__('Mark stay complete'))
                ->icon('heroicon-o-flag')
                ->color('secondary')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof Booking
                    && $this->record->booking_status === Booking::BOOKING_STATUS_OCCUPIED
                    && $this->record->isCheckOutTodayManila())
                ->action(function (): void {
                    $this->runBookingComplete();
                }),
            Action::make('viewBookingCancel')
                ->label(__('Cancel booking'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Cancel this booking?'))
                ->visible(fn (): bool => $this->record instanceof Booking
                    && ! in_array($this->record->booking_status, [Booking::BOOKING_STATUS_CANCELLED, Booking::BOOKING_STATUS_COMPLETED], true))
                ->action(function (): void {
                    $this->runBookingCancel();
                }),
        ];
    }

    protected function shouldShowPayBalanceForRecord(): bool
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking || $record->trashed()) {
            return false;
        }

        if ($record->payment_status === Booking::PAYMENT_STATUS_PAID
            || in_array($record->booking_status, [Booking::BOOKING_STATUS_CANCELLED, Booking::BOOKING_STATUS_COMPLETED], true)) {
            return false;
        }

        return (float) $record->balance > 0.009;
    }

    protected function payBalanceBlockedTooltipForRecord(): ?string
    {
        if (! $this->shouldShowPayBalanceForRecord()) {
            return null;
        }

        $record = $this->getRecord();
        if (! $record instanceof Booking) {
            return null;
        }

        $assessment = BookingFullBalancePayment::assess($record);
        if ($assessment['allowed']) {
            return null;
        }

        return $assessment['message']
            ?? match ($assessment['reason']) {
                BookingFullBalancePayment::REASON_NO_BALANCE => __('No remaining balance.'),
                default => __('This booking cannot be marked as paid yet.'),
            };
    }

    public function runBookingPayBalance(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking) {
            return;
        }

        try {
            BookingFullBalancePayment::record($record);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title(__('Cannot mark as paid'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }

        $this->afterBookingLifecycleMutation();
    }

    public function runBookingCheckIn(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking) {
            return;
        }

        try {
            BookingLifecycleActions::checkIn($record);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title(__('Cannot check in'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Booking checked in.'))
            ->success()
            ->send();

        $this->afterBookingLifecycleMutation();
    }

    public function runBookingComplete(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking) {
            return;
        }

        try {
            BookingLifecycleActions::complete($record);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title(__('Cannot complete'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Booking marked as completed.'))
            ->success()
            ->send();

        $this->afterBookingLifecycleMutation();
    }

    public function runBookingCancel(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof Booking) {
            return;
        }

        try {
            BookingLifecycleActions::cancel($record);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->title(__('Cannot cancel'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Booking cancelled.'))
            ->success()
            ->send();

        $this->afterBookingLifecycleMutation();
    }

    protected function afterBookingLifecycleMutation(): void
    {
        $this->record->refresh();

        if ($this->record instanceof Booking) {
            $this->redirect(BookingResource::calendarUrlForBooking($this->record));

            return;
        }

        if (method_exists($this, 'fillForm')) {
            $this->fillForm();
        }
    }
}
