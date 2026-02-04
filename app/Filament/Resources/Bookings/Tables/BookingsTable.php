<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Filament\Exports\BookingExporter;
use App\Models\Booking;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction('view')
            ->columns([
                ImageColumn::make('qr_code')
                    ->label('QR')
                    ->disk('public')
                    ->height(60)
                    ->width(60)
                    ->square()
                    ->url(fn ($record) => $record->qr_code ? Storage::disk('public')->url($record->qr_code) : null, true)
                    ->toggleable(),

                TextColumn::make('reference_number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('guest.first_name')
                    ->label('Guest')
                    ->formatStateUsing(fn ($record) => $record->guest?->full_name ?? '—')
                    ->searchable(['guest.first_name', 'guest.middle_name', 'guest.last_name', 'guest.email'])
                    ->sortable(),

                TextColumn::make('rooms.name')
                    ->label('Rooms')
                    ->formatStateUsing(fn ($record) => $record->rooms->pluck('name')->join(', ') ?: '—')
                    ->searchable(),

                TextColumn::make('venues.name')
                    ->label('Venues')
                    ->formatStateUsing(fn ($record) => $record->venues->pluck('name')->join(', ') ?: '—')
                    ->searchable(),

                TextColumn::make('check_in')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('check_out')
                    ->dateTime()
                    ->sortable(),
                
                TextColumn::make('no_of_days')
                    ->numeric()
                    ->sortable(),


                TextColumn::make('total_price')
                    ->money('PHP', true)
                    ->sortable(),

                BadgeColumn::make('status')
                    ->colors(Booking::statusColors())
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
                SelectFilter::make('status')
                    ->options(Booking::statusOptions()),
                Filter::make('booking_dates')
                    ->label('Filter by Dates')
                    ->form([
                        Select::make('mode')
                            ->label('Date type')
                            ->helperText('Choose which booking date to filter by.')
                            ->options([
                                'stay_overlap' => 'Stay dates (any overlap)',
                                'check_in' => 'Arrival date (check-in)',
                                'check_out' => 'Departure date (check-out)',
                                'created_at' => 'Booking created date',
                            ])
                            ->default('stay_overlap')
                            ->native(false),
                        Select::make('preset')
                            ->label('Quick dates')
                            ->options([
                                'today' => 'Today',
                                'next_7' => 'Next 7 days',
                                'next_30' => 'Next 30 days',
                                'this_month' => 'This month',
                                'last_month' => 'Last month',
                                'last_30' => 'Last 30 days',
                                'this_year' => 'This year',
                            ])
                            ->placeholder('Choose dates below')
                            ->native(false),
                        DatePicker::make('start')
                            ->label('From')
                            ->native(false),
                        DatePicker::make('end')
                            ->label('To')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        [$start, $end] = self::resolveDateRange($data);

                        if (! $start && ! $end) {
                            return $query;
                        }

                        if ($start && $end && $end->lessThan($start)) {
                            [$start, $end] = [$end, $start];
                        }

                        $start = $start?->startOfDay();
                        $end = $end?->endOfDay();
                        $mode = $data['mode'] ?? 'stay_overlap';

                        return match ($mode) {
                            'check_in' => $query
                                ->when($start, fn (Builder $q) => $q->where('check_in', '>=', $start))
                                ->when($end, fn (Builder $q) => $q->where('check_in', '<=', $end)),
                            'check_out' => $query
                                ->when($start, fn (Builder $q) => $q->where('check_out', '>=', $start))
                                ->when($end, fn (Builder $q) => $q->where('check_out', '<=', $end)),
                            'created_at' => $query
                                ->when($start, fn (Builder $q) => $q->where('created_at', '>=', $start))
                                ->when($end, fn (Builder $q) => $q->where('created_at', '<=', $end)),
                            default => $query
                                ->when($start && $end, fn (Builder $q) => $q
                                    ->where('check_in', '<', $end)
                                    ->where('check_out', '>', $start))
                                ->when($start && ! $end, fn (Builder $q) => $q->where('check_out', '>', $start))
                                ->when($end && ! $start, fn (Builder $q) => $q->where('check_in', '<', $end)),
                        };
                    })
                    ->indicateUsing(function (array $data): array {
                        [$start, $end] = self::resolveDateRange($data);

                        if (! $start && ! $end) {
                            return [];
                        }

                        $modeLabel = [
                            'stay_overlap' => 'Stay dates overlap',
                            'check_in' => 'Arrived during',
                            'check_out' => 'Left during',
                            'created_at' => 'Booked during',
                        ][$data['mode'] ?? 'stay_overlap'] ?? 'Stay dates overlap';

                        $startText = $start?->toDateString() ?? 'Any';
                        $endText = $end?->toDateString() ?? 'Any';

                        return ["{$modeLabel}: {$startText} → {$endText}"];
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->label('Export Bookings')
                    ->exporter(BookingExporter::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
            // ->toolbarActions([
            //     BulkActionGroup::make([
            //         DeleteBulkAction::make(),
            //     ]),
            // ])
    }

    private static function resolveDateRange(array $data): array
    {
        $preset = $data['preset'] ?? null;

        if ($preset) {
            return match ($preset) {
                'today' => [now()->startOfDay(), now()->endOfDay()],
                'next_7' => [now()->startOfDay(), now()->addDays(7)->endOfDay()],
                'next_30' => [now()->startOfDay(), now()->addDays(30)->endOfDay()],
                'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
                'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
                'last_30' => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
                'this_year' => [now()->startOfYear(), now()->endOfYear()],
                default => [null, null],
            };
        }

        $start = isset($data['start']) && $data['start']
            ? Carbon::parse($data['start'])
            : null;
        $end = isset($data['end']) && $data['end']
            ? Carbon::parse($data['end'])
            : null;

        return [$start, $end];
    }
}
