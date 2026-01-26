<?php

namespace App\Filament\Resources\Guests;

use App\Filament\Resources\Guests\Pages\CreateGuest;
use App\Filament\Resources\Guests\Pages\EditGuest;
use App\Filament\Resources\Guests\Pages\ListGuests;
use App\Filament\Resources\Guests\Tables\GuestsTable;
use App\Filament\Resources\Guests\Schemas\GuestForm;
use App\Models\Guest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class GuestResource extends Resource
{
    protected static ?string $model = Guest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $recordTitleAttribute = 'first_name';

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

    // Define relations (if you want to show bookings for a guest)
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    // Define resource pages
    public static function getPages(): array
    {
        return [
            'index' => ListGuests::route('/'),
            'create' => CreateGuest::route('/create'),
            'edit' => EditGuest::route('/{record}/edit'),
        ];
    }
}
