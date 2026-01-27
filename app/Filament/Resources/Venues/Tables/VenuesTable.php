<?php

namespace App\Filament\Resources\Venues\Tables;

use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;

class VenuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // ✅ Featured Image (Uses Spatie Media Library)
                ImageColumn::make('featured_image')
                    ->label('Featured')
                    ->getStateUsing(fn($record) => $record->getFirstMediaUrl('featured')),

                TextColumn::make('name')
                    ->label('Venue Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('price')
                    ->money('PHP', true)
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'available',
                        'danger' => 'booked',
                        'secondary' => 'maintenance',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Add filters here if needed (e.g., SelectFilter for status)
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}