<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DateTimePicker;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Booking Details')
                ->schema([
                    // Select the Guest (Searchable by Name or Email)
                    Select::make('guest_id')
                        ->relationship('guest', 'last_name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->first_name} {$record->last_name} ({$record->email})")
                        ->searchable()
                        ->preload()
                        ->required(),

                    // Select the Room OR Venue
                    Select::make('room_id')
                        ->relationship('room', 'name')
                        ->placeholder('Select a Room (Optional if Venue)')
                        ->searchable(),

                    Select::make('venue_id')
                        ->relationship('venue', 'name')
                        ->placeholder('Select a Venue (Optional if Room)')
                        ->searchable(),
                ])->columns(2),

            Section::make('Schedule & Pricing')
                ->schema([
                    DateTimePicker::make('check_in')->required(),
                    DateTimePicker::make('check_out')->required(),
                    TextInput::make('total_price')
                        ->numeric()
                        ->prefix('₱')
                        ->required(),
                    Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'occupied' => 'Occupied/Checked-in',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                        ])->default('pending')->required(),
                ])->columns(2),
        ]);
    }
}