<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Models\Room;
use App\Models\Guest;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Carbon\Carbon;
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
                    ->required()
                    ->native(false)
                    ->live()
                    // When check_in changes, recalculate days
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateDays($get, $set)),

                DateTimePicker::make('check_out')
                    ->required()
                    ->native(false)
                    ->live()
                    ->after(fn (Get $get) => $get('check_in')) // Validation: must be after check_in
                    // When check_out changes, recalculate days
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateDays($get, $set)),

                TextInput::make('no_of_days')
                    ->label('Number of Days')
                    ->numeric()
                    ->readOnly()
                    ->prefix('Days')
                    // Ensure the value is sent to the database even though it's readOnly
                    ->dehydrated(),

                

                TextInput::make('total_price')
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


    public static function calculateDays(Get $get, Set $set): void
    {
        $checkIn = $get('check_in');
        $checkOut = $get('check_out');

        if ($checkIn && $checkOut) {
            $startDate = Carbon::parse($checkIn);
            $endDate = Carbon::parse($checkOut);

            // Using diffInDays for absolute days. 
            // If you want "nights", use diffInDays. 
            // If you want "calendar days" (inclusive), add +1.
            $days = $startDate->diffInDays($endDate);

            $set('no_of_days', $days);
        }
    }
}
