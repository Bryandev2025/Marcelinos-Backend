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
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

use Filament\Actions\Exports\Enums\ExportFormat;


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
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('guest', function (Builder $guestQuery) use ($search): void {
                            $guestQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('middle_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
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
                        ToggleButtons::make('preset')
                            ->label('Quick dates')
                            ->options([
                                'today' => 'Today',
                                'next_7' => 'Next 7 days',
                                'next_30' => 'Next 30 days',
                                'this_month' => 'This month',
                                'last_month' => 'Last month',
                                'last_30' => 'Last 30 days',
                                'last_year' => 'Last year',
                                'last_2_years' => 'Last 2 years',
                                'this_year' => 'This year',
                            ])
                            ->inline()
                            ->visible(fn (Get $get) => ! (bool) $get('use_custom')),
                        Toggle::make('use_custom')
                            ->label('Use custom dates')
                            ->helperText('Turn this on to pick your own From/To dates.')
                            ->default(false)
                            ->live(),
                        DatePicker::make('start')
                            ->label('From')
                            ->native(false)
                            ->visible(fn (Get $get) => (bool) $get('use_custom')),
                        DatePicker::make('end')
                            ->label('To')
                            ->native(false)
                            ->visible(fn (Get $get) => (bool) $get('use_custom')),
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
                        return $query
                            ->when($start && $end, fn (Builder $q) => $q
                                ->where('check_in', '<', $end)
                                ->where('check_out', '>', $start))
                            ->when($start && ! $end, fn (Builder $q) => $q->where('check_out', '>', $start))
                            ->when($end && ! $start, fn (Builder $q) => $q->where('check_in', '<', $end));
                    })
                    ->indicateUsing(function (array $data): array {
                        [$start, $end] = self::resolveDateRange($data);

                        if (! $start && ! $end) {
                            return [];
                        }

                        $startText = $start?->toDateString() ?? 'Any';
                        $endText = $end?->toDateString() ?? 'Any';

                        return ["Dates: {$startText} → {$endText}"];
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->label('Export Bookings')
                    ->exporter(BookingExporter::class)
                    // ->query(fn (Builder $query) => self::applyFilters($query, request()->all()))
                    ->formats([
                        ExportFormat::Xlsx,
                        ExportFormat::Csv,
                    ])
                    ->fileName(function ($livewire, $format) {
                        $filters = $livewire->tableFilters ?? [];
                        $date = $filters['booking_dates'] ?? [];
                        [$start, $end] = BookingsTable::resolveDateRange($date);
                        $status = strtoupper($filters['status']['value'] ?? 'ALL');

                        $extension = $format === ExportFormat::Csv ? 'csv' : 'xlsx';
                        $period = '';

                        if (!empty($date['preset'])) {
                            switch ($date['preset']) {
                                case 'today':
                                    $period = now()->format('F d Y') . ' DAILY BOOKING REPORT';
                                    break;

                                case 'this_month':
                                    $period = now()->format('F') . " ALL BOOKINGS " . now()->year . " REPORT";
                                    break;

                                case 'next_7':
                                    $period = 'NEXT 7 DAYS ' . $start->format('F d Y') . ' TO ' . $end->format('F d Y') . ' BOOKINGS REPORT';
                                    break;

                                case 'next_30':
                                    $period = 'NEXT 30 DAYS ' . $start->format('F d Y') . ' TO ' . $end->format('F d Y') . ' BOOKINGS REPORT';
                                    break;

                                case 'last_30':
                                    $period = 'LAST 30 DAYS ' . $start->format('F d Y') . ' TO ' . $end->format('F d Y') . ' BOOKINGS REPORT';
                                    break;

                                case 'last_year':
                                    $period = now()->subYear()->year . ' ALL BOOKINGS REPORT';
                                    break;

                                case 'last_2_years':
                                    $period = (now()->subYears(2)->year) . '-' . (now()->subYear()->year) . ' ALL BOOKINGS REPORT';
                                    break;

                                case 'this_year':
                                    $period = now()->year . ' ALL BOOKINGS REPORT';
                                    break;

                                default:
                                    $period = 'ALL BOOKINGS';
                            }
                        } elseif ($start || $end) {
                            // Custom date range
                            if ($start && $end) {
                                $period = $start->format('F d Y') . ' TO ' . $end->format('F d Y') . ' BOOKINGS REPORT';
                            } elseif ($start) {
                                $period = 'FROM ' . $start->format('F d Y') . ' BOOKINGS REPORT';
                            } elseif ($end) {
                                $period = 'UP TO ' . $end->format('F d Y') . ' BOOKINGS REPORT';
                            }
                        } else {
                            // No filter at all: All bookings
                            $earliest = Booking::orderBy('check_in', 'asc')->first()?->check_in ?? now();
                            $latest = Booking::orderBy('check_out', 'desc')->first()?->check_out ?? now();
                            $period = 'ALL BOOKINGS ' . Carbon::parse($earliest)->format('F d Y') . ' TO ' . Carbon::parse($latest)->format('F d Y') . ' REPORT';
                        }

                        return $period . '.' . $extension;
                    }),
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
        $useCustom = (bool) ($data['use_custom'] ?? false);
        $preset = $useCustom ? null : ($data['preset'] ?? null);

        if ($preset) {
            return match ($preset) {
                'today' => [now()->startOfDay(), now()->endOfDay()],
                'next_7' => [now()->startOfDay(), now()->addDays(7)->endOfDay()],
                'next_30' => [now()->startOfDay(), now()->addDays(30)->endOfDay()],
                'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
                'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
                'last_30' => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
                'last_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
                'last_2_years' => [now()->subYears(2)->startOfYear(), now()->subYear()->endOfYear()],
                'this_year' => [now()->startOfYear(), now()->endOfYear()],
                default => [null, null],
            };
        }

        $start = $useCustom && isset($data['start']) && $data['start']
            ? Carbon::parse($data['start'])
            : null;
        $end = $useCustom && isset($data['end']) && $data['end']
            ? Carbon::parse($data['end'])
            : null;

        return [$start, $end];
    }

    public static function applyFilters(Builder $query, array $data): Builder
    {
        // Status filter
        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        // Date range filter (reusing your resolveDateRange method)
        [$start, $end] = self::resolveDateRange($data);

        if ($start && $end && $end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        $start = $start?->startOfDay();
        $end = $end?->endOfDay();

        return $query
            ->when($start && $end, fn (Builder $q) => $q->where('check_in', '<', $end)->where('check_out', '>', $start))
            ->when($start && ! $end, fn (Builder $q) => $q->where('check_out', '>', $start))
            ->when($end && ! $start, fn (Builder $q) => $q->where('check_in', '<', $end));
    }

}
