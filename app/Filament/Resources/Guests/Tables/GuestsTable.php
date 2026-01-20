<?php

namespace App\Filament\Resources\Guests\Tables;

use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class GuestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Full Name using model accessor
                TextColumn::make('full_name')
                    ->label('Full Name')
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email Address')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('contact_num')
                    ->label('Contact Number')
                    ->searchable(),

                TextColumn::make('gender')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('id_type')
                    ->label('ID Type')
                    ->searchable(),

                TextColumn::make('id_number')
                    ->label('ID Number')
                    ->searchable(),

                IconColumn::make('is_international')
                    ->label('International')
                    ->boolean(),

                TextColumn::make('country')
                    ->searchable()
                    ->sortable(),

                // Local (Philippines) Address
                TextColumn::make('province')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('municipality')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('barangay')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                // International Address
                TextColumn::make('city')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('state_region')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                // Timestamps
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Example: filter local or international guests
               SelectFilter::make('is_international')
                    ->label('Guest Type')
                    ->options([
                        0 => 'Local',
                        1 => 'International',
                    ]),
                // Filter by gender
               SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                        'other' => 'Other',
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
