<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Filament\Exports\BookingExporter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('guest.first_name')
                    ->label('Guest')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('room.name')
                    ->label('Room')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('check_in')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('check_out')
                    ->dateTime()
                    ->sortable(),
                
                TextColumn::make('no_of_days')
                    ->dateTime()
                    ->sortable(),


                TextColumn::make('total_price')
                    ->money('PHP', true)
                    ->sortable(),

                BadgeColumn::make('status')
                    ->colors([
                        'primary' => 'pending',
                        'success' => 'confirmed',
                        'warning' => 'occupied',
                        'secondary' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->sortable(),

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
            ->headerActions([
                ExportAction::make()
                    ->label('Export Bookings')
                    ->exporter(BookingExporter::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
            // ->toolbarActions([
            //     BulkActionGroup::make([
            //         DeleteBulkAction::make(),
            //     ]),
            // ])
    }
}
