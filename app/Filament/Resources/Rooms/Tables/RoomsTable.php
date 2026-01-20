<?php

namespace App\Filament\Resources\Rooms\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // 1. MAIN IMAGE THUMBNAIL
                ImageColumn::make('mainImage.url')
                    ->label('Photo')
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder-room.jpg')),

                TextColumn::make('name')
                    ->searchable()
                    ->label('Name')
                    ->extraAttributes(['class' => 'font-bold']),

                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'info' => 'standard',
                        'warning' => 'family',
                        'success' => 'deluxe',
                    ]),

                // 2. PRICING WITH CURRENCY
                TextColumn::make('price')
                    ->money('PHP') // Formats as ₱
                    ->sortable(),

                // 3. COLOR-CODED STATUS
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success', // Green
                        'occupied' => 'danger',  // Red
                        'cleaning' => 'warning', // Yellow/Orange
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filter by status
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'available' => 'Available',
                        'occupied'  => 'Occupied',
                        'cleaning'  => 'Cleaning',
                    ]),

                // Filter by type
                SelectFilter::make('type')
                    ->label('Room Type')
                    ->options([
                        'standard' => 'Standard',
                        'family'   => 'Family',
                        'deluxe'   => 'Deluxe',
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
