<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use JeffersonGoncalves\Filament\QrCodeField\Forms\Components\QrCodeInput;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    public function getHeading(): string
    {
        return 'Bookings list';
    }

    public function getSubheading(): ?string
    {
        return 'Search, filter, and manage reservations in one place.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendarView')
                ->label('Booking Calendar')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->url(BookingResource::getUrl('roomCalendar')),
            CreateAction::make(),
            Action::make('scanQr')
                ->label('Scan QR')
                ->icon('heroicon-o-qr-code')
                ->color('primary')
                ->modalHeading('Scan Booking QR Code')
                ->modalDescription('Open your camera and hold the guest\'s booking QR code within the frame to look up their reservation instantly.')
                ->modalWidth('md')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->form([
                    QrCodeInput::make('qr_payload')
                        ->hiddenLabel()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?string $state, $livewire): void {
                            $payload = $state;

                            if (! $payload) {
                                Notification::make()
                                    ->title('No QR code data found.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $decoded = json_decode($payload, true);

                            $bookingId = null;
                            $reference = null;

                            if (is_array($decoded)) {
                                // Prefer explicit fields from JSON payload
                                $bookingId = $decoded['booking_id'] ?? null;
                                $reference = $decoded['reference_number'] ?? ($decoded['reference'] ?? null);
                            }

                            // Fallback: if no reference in JSON, treat raw payload as reference string
                            $reference = $reference ?: trim($payload);

                            $booking = null;

                            if ($bookingId) {
                                $booking = Booking::find($bookingId);
                            }

                            if (! $booking && $reference) {
                                $booking = Booking::where('reference_number', $reference)->first();
                            }

                            if (! $booking) {
                                Notification::make()
                                    ->title('Booking not found.')
                                    ->body('The scanned QR code did not match any booking. Please try again.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $livewire->redirect(BookingResource::getUrl('view', ['record' => $booking]));
                        }),
                ])
                ->action(fn() => null),
        ];
    }
}
