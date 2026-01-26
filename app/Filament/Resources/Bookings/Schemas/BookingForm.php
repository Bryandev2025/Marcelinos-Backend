<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\Guest;
use App\Models\Room;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('guest_id')
                    ->label('Guest')
                    ->options(Guest::all()->pluck('first_name', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('room_id')
                    ->label('Room')
                    ->options(Room::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                DateTimePicker::make('check_in')
                    ->required(),

                DateTimePicker::make('check_out')
                    ->required(),

                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('₱'),

                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'occupied' => 'Occupied',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending')
                    ->required(),

                TextInput::make('reference_number')
                    ->label('Reference Number')
                    ->disabled()
            ]);
    }
}
