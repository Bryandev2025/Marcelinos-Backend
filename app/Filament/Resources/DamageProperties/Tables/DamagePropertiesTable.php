<?php

namespace App\Filament\Resources\DamageProperties\Tables;

use App\Filament\Resources\DamageProperties\DamagePropertyResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DamagePropertiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction('view')
            ->recordUrl(fn ($record) => DamagePropertyResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Property')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->iconColor('primary')
                    ->weight('medium'),
                TextColumn::make('default_charge')
                    ->label('Default charge')
                    ->placeholder('—')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('rooms_count')
                    ->label('Assigned rooms')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => (string) ((int) ($state ?? 0)))
                    ->color(fn ($state): string => ((int) ($state ?? 0)) > 0 ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
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

