<?php

namespace App\Filament\Resources\Guests;

use App\Filament\Resources\Concerns\ResolvesTrashedRecordRoutes;
use App\Filament\Resources\Guests\Pages\CreateGuest;
use App\Filament\Resources\Guests\Pages\EditGuest;
use App\Filament\Resources\Guests\Pages\ListGuests;
use App\Filament\Resources\Guests\Pages\ViewGuest;
use App\Filament\Resources\Guests\RelationManagers\BookingsRelationManager;
use App\Filament\Resources\Guests\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\Guests\Schemas\GuestForm;
use App\Filament\Resources\Guests\Tables\GuestsTable;
use App\Models\Booking;
use App\Models\Guest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuestResource extends Resource
{
    use ResolvesTrashedRecordRoutes;

    protected static ?string $model = Guest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Guests';

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getNavigationBadge(): ?string
    {
        $count = Booking::query()
            ->where('damage_settlement_status', Booking::DAMAGE_SETTLEMENT_STATUS_PENDING)
            ->distinct('guest_id')
            ->count('guest_id');

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'first_name',
            'middle_name',
            'last_name',
            'email',
            'contact_num',
        ];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->full_name;
    }

    // Form configuration
    public static function form(Schema $schema): Schema
    {
        return GuestForm::configure($schema);
    }

    // Table configuration
    public static function table(Table $table): Table
    {
        return GuestsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'bookings as pending_settlement_bookings_count' => fn (Builder $query): Builder => $query
                    ->where('damage_settlement_status', Booking::DAMAGE_SETTLEMENT_STATUS_PENDING),
            ]);
    }

    // Define relations (if you want to show bookings for a guest)
    public static function getRelations(): array
    {
        return [
            BookingsRelationManager::class,
            ReviewsRelationManager::class,
        ];
    }

    // Define resource pages
    public static function getPages(): array
    {
        return [
            'index' => ListGuests::route('/'),
            'create' => CreateGuest::route('/create'),
            'edit' => EditGuest::route('/{record}/edit'),
            'view' => ViewGuest::route('/{record}'),
        ];
    }
}
