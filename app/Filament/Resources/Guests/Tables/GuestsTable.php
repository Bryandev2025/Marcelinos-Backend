<?php

namespace App\Filament\Resources\Guests\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;

class GuestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // ✅ ID Verification Image
                ImageColumn::make('id_verification')
                    ->label('ID Verification')
                    ->getStateUsing(fn($record) => $record->getFirstMediaUrl('id_verification')),

                TextColumn::make('first_name')->searchable(),
                TextColumn::make('middle_name')->searchable(),
                TextColumn::make('last_name')->searchable(),
                TextColumn::make('contact_num')->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),

                // ✅ Gender Badge
                TextColumn::make('gender')
                    ->badge()
                    ->colors([
                        'primary' => 'male',
                        'warning' => 'female',
                        'secondary' => 'other',
                    ]),

                // ✅ International Guest Icon
                IconColumn::make('is_international')
                    ->boolean()
                    ->label('International'),

                TextColumn::make('country')->searchable(),
                TextColumn::make('province')->searchable(),
                TextColumn::make('municipality')->searchable(),
                TextColumn::make('barangay')->searchable(),
                TextColumn::make('city')->searchable(),
                TextColumn::make('zip_code')->searchable(),

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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
