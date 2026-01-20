<?php

namespace App\Filament\Resources\Venues\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class VenuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // 1. MAIN IMAGE THUMBNAIL
                ImageColumn::make('mainImage.url')
                    ->label('Photo')
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder-venue.jpg')),

                TextColumn::make('name')
                    ->searchable()
                    ->label('Venue Name')
                    ->extraAttributes(['class' => 'font-bold']),

                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable()
                    ->label('Capacity')
                    ->alignCenter(),

                // 2. PRICE FORMATTED
                TextColumn::make('price')
                    ->money('PHP') // Displays ₱
                    ->sortable()
                    ->label('Price'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Optional filters (e.g., capacity or price range)
                SelectFilter::make('capacity')
                    ->label('Capacity')
                    ->options([
                        '50' => 'Up to 50',
                        '100' => '51 - 100',
                        '200' => '101 - 200',
                        '500' => '201 - 500',
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
