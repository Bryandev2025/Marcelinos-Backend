<?php

namespace App\Filament\Resources\Rooms\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('capacity')
                    ->required()
                    ->numeric(),
                Select::make('type')
                    ->options(['standard' => 'Standard', 'family' => 'Family', 'deluxe' => 'Deluxe'])
                    ->required(),
                TextInput::make('price_per_night')
                    ->required()
                    ->numeric(),
                Select::make('status')
                    ->options(['available' => 'Available', 'occupied' => 'Occupied', 'maintenance' => 'Maintenance'])
                    ->default('available')
                    ->required(),
            ]);
    }
}
