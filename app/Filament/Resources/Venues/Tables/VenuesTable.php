<?php

namespace App\Filament\Resources\Venues\Tables;

use App\Models\Venue;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;

class VenuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // ✅ Featured Image (Uses Spatie Media Library)
                SpatieMediaLibraryImageColumn::make('featured_image')
                    ->label('Featured')
                    ->collection('featured')
                    ->circular(),

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
                    ->colors(Venue::statusColors())
                    ->formatStateUsing(fn (string $state): string => Venue::statusOptions()[$state] ?? ucfirst($state)),

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
                ])
                ->visible(fn () => Auth::user() && Auth::user()->role === 'admin'),
            ]);
    }
}