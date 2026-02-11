<?php

namespace App\Filament\Resources\Rooms\Tables;

use App\Models\Room;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction('view')
            ->recordUrl(fn ($record) => \App\Filament\Resources\Rooms\RoomResource::getUrl('view', ['record' => $record]))
            ->columns([
                // ✅ Featured Image
                SpatieMediaLibraryImageColumn::make('featured_image')
                    ->label('Featured')
                    ->circular()
                    ->collection('featured'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Room::typeOptions()[$state] ?? $state),

                TextColumn::make('price')
                    ->money('PHP', true)
                    ->sortable(),

                // Removed status column - availability determined by bookings
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
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ])
                ->visible(fn () => Auth::user() && Auth::user()->role === 'admin'),
            ]);
    }
}