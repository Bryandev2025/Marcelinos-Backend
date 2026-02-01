<?php

namespace App\Filament\Exports;

use App\Models\Booking;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class BookingExporter extends Exporter
{
    protected static ?string $model = Booking::class;

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('guest.first_name')->label('Guest First Name'),
            ExportColumn::make('guest.last_name')->label('Guest Last Name'),
            ExportColumn::make('rooms')
                ->label('Rooms')
                ->formatStateUsing(fn (Booking $record) => $record->rooms->pluck('name')->join(', ')),
            ExportColumn::make('venues')
                ->label('Venues')
                ->formatStateUsing(fn (Booking $record) => $record->venues->pluck('name')->join(', ')),
            ExportColumn::make('check_in')
                ->formatStateUsing(fn ($state) => optional($state)?->toDateTimeString() ?? $state),
            ExportColumn::make('check_out')
                ->formatStateUsing(fn ($state) => optional($state)?->toDateTimeString() ?? $state),
            ExportColumn::make('total_price')
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2, '.', '')),
            ExportColumn::make('reference_number'),
            ExportColumn::make('status'),
            ExportColumn::make('created_at')
                ->formatStateUsing(fn ($state) => optional($state)?->toDateTimeString() ?? $state),
            ExportColumn::make('updated_at')
                ->formatStateUsing(fn ($state) => optional($state)?->toDateTimeString() ?? $state),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your booking export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
