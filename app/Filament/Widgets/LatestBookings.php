<?php

namespace App\Filament\Widgets;

use Filament\Widgets\TableWidget;
use App\Models\Booking;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class LatestBookings extends TableWidget
{
    protected static ?int $sort = 2;
    
    protected static ?string $heading = 'Latest Bookings';

    // Make this widget half-width (right side)
    protected int | string | array $columnSpan = 1;

    // Corrected method signature
    protected function getTableQuery(): Builder|Relation|null
    {
        return Booking::query()->with('guest')->latest();
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('id')
                ->label('Booking ID')
                ->sortable(),
            
            Tables\Columns\TextColumn::make('guest.full_name')
                ->label('Customer')
                ->formatStateUsing(fn ($record) => $record->guest?->full_name ?? '—'),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Date')
                ->dateTime('M d, Y H:i'),

            Tables\Columns\BadgeColumn::make('status')
                ->label('Status')
                ->colors(Booking::statusColors()),
        ];
    }
}