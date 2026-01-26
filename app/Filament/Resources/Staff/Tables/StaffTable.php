<?php

namespace App\Filament\Resources\Staff\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                    ->sortable()
                    ->extraAttributes(['class' => 'font-bold']),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->extraAttributes(['class' => 'text-gray-600']),

                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->colors([
                        'success' => fn($state) => $state === 'staff',
                        'primary' => fn($state) => $state === 'admin',
                    ])
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Status')
                    ->onColor('success')      // Green
                    ->offColor('danger')      // Red
                    ->onIcon('heroicon-o-check-badge')
                    ->offIcon('heroicon-o-x-mark')
                    ->sortable(),

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

                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
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
