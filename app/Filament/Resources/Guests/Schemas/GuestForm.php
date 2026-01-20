<?php

namespace App\Filament\Resources\Guests\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class GuestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ------------------
                // Basic Info
                // ------------------
                Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('middle_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('contact_num')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                            ])
                            ->nullable(),
                    ]),

                // ------------------
                // Identification
                // ------------------
               Section::make('Identification')
                    ->schema([
                       TextInput::make('id_type')
                            ->label('ID Type')
                            ->required()
                            ->maxLength(100),
                       TextInput::make('id_number')
                            ->label('ID Number')
                            ->required()
                            ->maxLength(100),
                    ]),

                // ------------------
                // Guest Type
                // ------------------
               Section::make('Guest Type')
                    ->schema([
                       Toggle::make('is_international')
                            ->label('International Guest')
                            ->default(false),
                       TextInput::make('country')
                            ->required()
                            ->maxLength(100)
                            ->default('Philippines'),
                    ]),

                // ------------------
                // Local Address
                // ------------------
               Section::make('Local Address')
                    ->schema([
                       TextInput::make('province')->maxLength(100),
                       TextInput::make('municipality')->maxLength(100),
                       TextInput::make('barangay')->maxLength(100),
                    ])
                    ->visible(fn ($get) => !$get('is_international')),

                // ------------------
                // International Address
                // ------------------
               Section::make('International Address')
                    ->schema([
                       TextInput::make('city')->maxLength(100),
                       TextInput::make('state_region')->maxLength(100),
                    ])
                    ->visible(fn ($get) => $get('is_international')),

                // ------------------
                // Optional: ID Upload
                // ------------------
                //FileUpload::make('id_image_path')
                //     ->label('Upload ID')
                //     ->image()
                //     ->directory('guest-ids'),
            ]);
    }
}
