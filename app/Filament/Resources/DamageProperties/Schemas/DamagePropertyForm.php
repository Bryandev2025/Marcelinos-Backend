<?php

namespace App\Filament\Resources\DamageProperties\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DamagePropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Damage property details')
                    ->description('Create reusable damaged-property items that can be manually assigned to rooms.')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->schema([
                        TextInput::make('name')
                            ->label('Property name')
                            ->placeholder('e.g. Television, Aircon Remote, Towel')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('default_charge')
                            ->label('Default charge')
                            ->placeholder('e.g. Php 500.00'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Inactive properties are hidden from room assignment lists.'),
                    ]),
            ]);
    }
}

