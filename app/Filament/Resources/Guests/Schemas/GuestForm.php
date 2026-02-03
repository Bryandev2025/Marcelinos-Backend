<?php

namespace App\Filament\Resources\Guests\Schemas;

use App\Models\Guest;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class GuestForm
{
     public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')->required(),
                TextInput::make('middle_name'),
                TextInput::make('last_name')->required(),
                TextInput::make('contact_num')->required(),
                TextInput::make('email')
                    ->required()
                    ->email()
                    ->unique(ignoreRecord: true),
                Select::make('gender')
                    ->options(Guest::genderOptions())
                    ->required(),
                Toggle::make('is_international')
                    ->label('International')
                    ->required()
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state): void {
                        if ($state) {
                            $set('province', null);
                            $set('municipality', null);
                            $set('barangay', null);
                            $set('city', null);
                            $set('zip_code', null);
                        } else {
                            $set('country', 'Philippines');
                        }
                    }),
                TextInput::make('country')
                    ->default('Philippines')
                    ->required(fn (Get $get) => (bool) $get('is_international'))
                    ->visible(fn (Get $get) => (bool) $get('is_international')),
                TextInput::make('province')
                    ->visible(fn (Get $get) => ! $get('is_international')),
                TextInput::make('municipality')
                    ->visible(fn (Get $get) => ! $get('is_international')),
                TextInput::make('barangay')
                    ->visible(fn (Get $get) => ! $get('is_international')),
                TextInput::make('city')
                    ->visible(fn (Get $get) => ! $get('is_international')),
                TextInput::make('zip_code')
                    ->visible(fn (Get $get) => ! $get('is_international')),
            ]);
    }
}
