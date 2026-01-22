<?php

namespace App\Filament\Resources\Staff\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable()
                    ->extraAttributes(['class' => 'font-bold']),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->extraAttributes(['class' => 'text-gray-600']),

                TextColumn::make('role')
                    ->label('Role')
                    ->sortable()
                    ->badge()
                    ->colors([
                        'success' => 'staff',
                        'primary' => 'admin',
                    ]),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'staff' => 'Staff',
                        'admin' => 'Admin',
                    ]),
            ])
            ->recordActions([
                EditAction::make(), // ✅ edit button
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(), // ✅ checkbox + delete
                ]),
            ]);
    }
}
