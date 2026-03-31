<?php

namespace App\Filament\Resources\Staff\Tables;

use App\Models\User;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\Action;
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

                TextColumn::make('permissions')
                    ->label('Privileges')
                    ->getStateUsing(function (User $record): string {
                        if ($record->role === 'admin') {
                            return 'All privileges';
                        }

                        $permissions = $record->permissions;
                        if (is_string($permissions)) {
                            $decoded = json_decode($permissions, true);
                            $permissions = is_array($decoded) ? $decoded : [];
                        }

                        if (! is_array($permissions) || $permissions === []) {
                            return 'No privileges';
                        }

                        // CheckboxList can persist as:
                        // 1) list of selected keys: ['manage_rooms', 'manage_bookings']
                        // 2) map of key => bool: ['manage_rooms' => true, 'manage_bookings' => false]
                        $isAssoc = array_keys($permissions) !== range(0, count($permissions) - 1);

                        $selectedKeys = $isAssoc
                            ? array_keys(array_filter($permissions, fn ($enabled) => (bool) $enabled))
                            : array_values(array_filter($permissions, fn ($key) => is_string($key) && $key !== ''));

                        if ($selectedKeys === []) {
                            return 'No privileges';
                        }

                        return (string) count($selectedKeys) . ' selected';
                    })
                    ->badge()
                    ->color(fn (string $state) => $state === 'No privileges' ? 'gray' : 'success'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),

                    TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
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
                Action::make('changeStatus')
                    ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) =>
                        $record->is_active
                            ? 'heroicon-o-x-circle'
                            : 'heroicon-o-check-circle'
                    )
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) =>
                        $record->is_active ? 'Deactivate Staff' : 'Activate Staff'
                    )
                    ->modalDescription(fn ($record) =>
                        $record->is_active
                            ? 'Are you sure you want to deactivate this staff member? They will no longer be able to log in.'
                            : 'Are you sure you want to activate this staff member? They will regain access to the system.'
                    )
                    ->modalSubmitActionLabel(fn ($record) =>
                        $record->is_active ? 'Yes, Deactivate' : 'Yes, Activate'
                    )
                    ->action(fn ($record) =>
                        $record->update(['is_active' => ! $record->is_active])
                    ),
                    EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
