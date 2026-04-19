<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Concerns\HasBookingPayBalanceHeaderAction;
use App\Filament\Resources\Bookings\Concerns\InteractsWithBookingOperations;
use App\Models\Booking;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewBooking extends ViewRecord
{
    use HasBookingPayBalanceHeaderAction;
    use InteractsWithBookingOperations;

    protected static string $resource = BookingResource::class;

    public function form(Schema $schema): Schema
    {
        $configured = parent::form($schema);
        $components = $configured->getComponents();

        return $configured->components([
            $this->makeBookingOperationsSectionForView(),
            ...array_values($components),
        ]);
    }

    public function getHeading(): string
    {
        return 'Booking details';
    }

    public function getSubheading(): ?string
    {
        if (! $this->record instanceof Booking) {
            return null;
        }

        $guestName = $this->record->guest?->full_name ?: 'Unknown guest';

        return "{$this->record->reference_number} - {$guestName}";
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->bookingLifecycleHeaderActionsForView(),
            EditAction::make(),
        ];
    }
}
