<?php

namespace App\Filament\Resources\Reviews\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('guest.full_name')
                    ->label('Guest')
                    ->searchable(),

                TextColumn::make('reviewable.name')
                    ->label('Target')
                    ->formatStateUsing(fn ($state, $record) => $record->is_site_review ? 'Site' : ($state ?? '')),

                TextColumn::make('rating')
                    ->badge()
                    ->colors([
                        'danger' => fn ($state) => (int) $state <= 2,
                        'warning' => fn ($state) => (int) $state === 3,
                        'success' => fn ($state) => (int) $state >= 4,
                    ]),

                TextColumn::make('is_site_review')
                    ->label('Site')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                    ->color(fn ($state) => $state ? 'primary' : 'secondary'),

                ToggleColumn::make('is_approved')
                    ->label('Approved'),

                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                TernaryFilter::make('is_site_review')
                    ->label('Site Review')
                    ->trueLabel('Site')
                    ->falseLabel('Room/Venue'),

                TernaryFilter::make('is_approved')
                    ->label('Approved')
                    ->trueLabel('Approved')
                    ->falseLabel('Pending'),

                SelectFilter::make('rating')
                    ->options([
                        1 => '1',
                        2 => '2',
                        3 => '3',
                        4 => '4',
                        5 => '5',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
