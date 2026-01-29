<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Carbon\Carbon;
use App\Models\Room;
use App\Models\Guest;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('guest_id')
                ->label('Guest')
                ->options(Guest::all()->pluck('first_name', 'id'))
                ->searchable()
                ->required(),

            Select::make('room_id')
                ->label('Room')
                ->options(Room::all()->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updatePricing($get, $set)),

            DateTimePicker::make('check_in')
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updatePricing($get, $set)),

            DateTimePicker::make('check_out')
                ->required()
                ->native(false)
                ->live()
                ->after(fn (Get $get) => $get('check_in'))
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updatePricing($get, $set)),

            TextInput::make('no_of_days')
                ->label('Stay Duration')
                ->numeric() 
                ->suffix(' days') 
                ->readOnly()
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

    public static function updatePricing(Get $get, Set $set): void
    {
        self::calculateDays($get, $set);
        self::calculateTotal($get, $set);
    }

    public static function calculateDays(Get $get, Set $set): void
    {
        $checkIn = $get('check_in');
        $checkOut = $get('check_out');

        if (!$checkIn || !$checkOut) {
            $set('no_of_days', 0);
            return;
        }

        try {
            $startDate = Carbon::parse($checkIn);
            $endDate = Carbon::parse($checkOut);
            $days = (int) $startDate->diffInDays($endDate);
            
            $set('no_of_days', max(1, $days)); // Store integer 1, 2, etc.
        } catch (\Exception $e) {
            $set('no_of_days', 0);
        }
    }

    public static function calculateTotal(Get $get, Set $set): void
    {
        $roomId = $get('room_id');
        $days = (int) $get('no_of_days'); 

        if ($roomId && $days > 0) {
            $room = Room::find($roomId);
            if ($room && $room->price) {
                $set('total_price', $room->price * $days);
            }
        } else {
            $set('total_price', 0);
        }
    }
}