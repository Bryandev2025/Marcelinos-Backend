<?php

namespace App\Filament\Resources\Bookings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // 1. READABLE GUEST NAME (Instead of guest_id)
                TextColumn::make('guest.last_name')
                    ->label('Guest Name')
                    ->description(fn ($record) => $record->guest->first_name . ' ' . ($record->guest->middle_name ?? ''))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                // 2. ROOM OR VENUE IDENTIFIER
                TextColumn::make('room.name')
                    ->label('Room/Venue')
                    ->default(fn ($record) => $record->venue ? $record->venue->name : 'N/A')
                    ->description(fn ($record) => $record->room ? 'Room' : ($record->venue ? 'Venue' : '')),

                TextColumn::make('check_in')
                    ->dateTime('M d, Y h:i A') // Filipino readable format
                    ->sortable(),

                TextColumn::make('check_out')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),

                // 3. PHILIPPINE PESO FORMATTING
                TextColumn::make('total_price')
                    ->money('PHP')
                    ->sortable(),

                // 4. COLOR-CODED BADGES
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'occupied' => 'success',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        'rescheduled' => 'warning',
                    }),

                TextColumn::make('payment_reference')
                    ->label('Ref #')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'occupied' => 'Checked-in',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}