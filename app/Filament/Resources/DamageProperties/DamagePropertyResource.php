<?php

namespace App\Filament\Resources\DamageProperties;

use App\Filament\Resources\DamageProperties\Pages\CreateDamageProperty;
use App\Filament\Resources\DamageProperties\Pages\EditDamageProperty;
use App\Filament\Resources\DamageProperties\Pages\ListDamageProperties;
use App\Filament\Resources\DamageProperties\Pages\ViewDamageProperty;
use App\Filament\Resources\DamageProperties\Schemas\DamagePropertyForm;
use App\Filament\Resources\DamageProperties\Tables\DamagePropertiesTable;
use App\Models\DamageProperty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DamagePropertyResource extends Resource
{
    protected static ?string $model = DamageProperty::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|\UnitEnum|null $navigationGroup = 'Properties';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Damage Properties';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return (string) DamageProperty::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function form(Schema $schema): Schema
    {
        return DamagePropertyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DamagePropertiesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['rooms']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDamageProperties::route('/'),
            'create' => CreateDamageProperty::route('/create'),
            'edit' => EditDamageProperty::route('/{record}/edit'),
            'view' => ViewDamageProperty::route('/{record}'),
        ];
    }
}

