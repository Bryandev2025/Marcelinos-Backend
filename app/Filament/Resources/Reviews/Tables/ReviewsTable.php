<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Models\Review;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
            ->recordAction('view')
            ->recordUrl(fn ($record) => \App\Filament\Resources\Reviews\ReviewResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('guest.full_name')
                    ->label('Guest')
                    ->formatStateUsing(fn ($record) => $record->guest?->full_name ?? '—')
                    ->searchable(['guest.first_name', 'guest.middle_name', 'guest.last_name']),


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
                    ->dateTime(),

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
                    ->falseLabel('Unpaid'),

                SelectFilter::make('rating')
                    ->options(Review::ratingOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
