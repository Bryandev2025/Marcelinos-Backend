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

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('scanQr')
                ->label('Scan QR')
                ->icon('heroicon-o-qr-code')
                ->form([
                    QrCodeInput::make('qr_payload')
                        ->label('Scan booking QR')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $payload = $data['qr_payload'] ?? null;

                    if (!$payload) {
                        Notification::make()
                            ->title('No QR code data found.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $decoded = json_decode($payload, true);
                    $bookingId = is_array($decoded) ? ($decoded['booking_id'] ?? null) : null;
                    $reference = is_array($decoded) ? ($decoded['reference'] ?? null) : null;

                    $booking = Booking::query()
                        ->when($bookingId, fn ($query) => $query->whereKey($bookingId))
                        ->when(!$bookingId && $reference, fn ($query) => $query->where('reference_number', $reference))
                        ->first();

                    if (!$booking) {
                        Notification::make()
                            ->title('Booking not found.')
                            ->danger()
                            ->send();
                        return;
                    }

                    return redirect(BookingResource::getUrl('edit', ['record' => $booking]));
                }),
        ];
    }
}
