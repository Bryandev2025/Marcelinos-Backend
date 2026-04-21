<?php

namespace App\Filament\Resources\Bookings\Pages;

class VenueCalendar extends RoomCalendar
{
    protected static ?string $title = 'Venue Calendar';

    protected static ?string $breadcrumb = 'Venue Calendar';

    public function mount(): void
    {
        $this->reservationFilter = self::RESERVATION_VENUE;

        parent::mount();
    }
}
