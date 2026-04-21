<?php

namespace App\Filament\Resources\Bookings;

use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\RoomCalendar;
use App\Filament\Resources\Bookings\Pages\VenueCalendar;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Bookings\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Bookings\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\Bookings\RelationManagers\RoomLinesRelationManager;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Filament\Resources\Concerns\ResolvesTrashedRecordRoutes;
use App\Filament\Widgets\BookingStatsOverview;
use App\Models\Booking;
use BackedEnum;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingResource extends Resource
{
    use ResolvesTrashedRecordRoutes;

    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Bookings';

    protected static ?string $recordTitleAttribute = 'reference_number';

    public static function form(Schema $schema): Schema
    {
        return BookingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookingsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'guest:id,first_name,middle_name,last_name,email,contact_num,gender,is_international,country,region,province,municipality,barangay',
                'rooms' => fn ($q) => $q->with(['bedSpecifications']),
                'roomLines',
                'venues:id,name',
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ReviewsRelationManager::class,
            PaymentsRelationManager::class,
            RoomLinesRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            BookingStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => RoomCalendar::route('/'),
            'roomCalendar' => RoomCalendar::route('/room-calendar'),
            'venueCalendar' => VenueCalendar::route('/venue-calendar'),
            'list' => ListBookings::route('/list'),
            'create' => CreateBooking::route('/create'),
            'edit' => EditBooking::route('/{record}/edit'),
            'view' => ViewBooking::route('/{record}'),
        ];
    }

    public static function calendarUrlForBooking(Booking $booking): string
    {
        $booking->loadMissing(['rooms:id,type', 'roomLines', 'venues:id']);

        $checkIn = $booking->check_in instanceof \Carbon\CarbonInterface
            ? $booking->check_in->copy()
            : Carbon::parse($booking->check_in);

        $month = (int) $checkIn->month;
        $year = (int) $checkIn->year;
        $modalDate = $checkIn->toDateString();

        $reservationFilter = RoomCalendar::RESERVATION_ROOM;
        $modalType = null;

        $hasRooms = $booking->rooms->isNotEmpty() || $booking->roomLines->isNotEmpty();
        $hasVenues = $booking->venues->isNotEmpty();

        if ($hasRooms && $hasVenues) {
            $reservationFilter = RoomCalendar::RESERVATION_BOTH;
        } elseif ($hasVenues) {
            $reservationFilter = RoomCalendar::RESERVATION_VENUE;
        }

        $roomType = $booking->rooms->first()?->type ?: $booking->roomLines->first()?->room_type;
        if ($reservationFilter !== RoomCalendar::RESERVATION_VENUE && is_string($roomType) && trim($roomType) !== '') {
            $modalType = $roomType;
        } elseif ($hasVenues) {
            $modalType = (string) $booking->venues->first()->id;
        }

        return static::getUrl('index', array_filter([
            'month' => $month,
            'year' => $year,
            'reservationFilter' => $reservationFilter,
            'modalDate' => $modalDate,
            'modalType' => $modalType,
        ], fn ($v) => $v !== null && $v !== ''));
    }
}
