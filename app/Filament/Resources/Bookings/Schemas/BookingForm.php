<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Carbon\Carbon;
use App\Models\Room;
use App\Models\Venue;
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

            Select::make('rooms')
                ->label('Rooms')
                ->relationship('rooms', 'name')
                ->multiple()
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updatePricing($get, $set)),

            Select::make('venues')
                ->label('Venues')
                ->relationship('venues', 'name')
                ->multiple()
                ->searchable()
                ->preload()
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
        $roomIds = $get('rooms') ?? [];
        $venueIds = $get('venues') ?? [];
        $days = (int) $get('no_of_days');

        $roomIds = is_array($roomIds) ? $roomIds : [$roomIds];
        $venueIds = is_array($venueIds) ? $venueIds : [$venueIds];
        $roomIds = array_filter($roomIds);
        $venueIds = array_filter($venueIds);

        if (($roomIds || $venueIds) && $days > 0) {
            $roomsTotal = Room::whereIn('id', $roomIds)->sum('price');
            $venuesTotal = Venue::whereIn('id', $venueIds)->sum('price');
            $set('total_price', ($roomsTotal + $venuesTotal) * $days);
        } else {
            $set('total_price', 0);
        }
    }
}