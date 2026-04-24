<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Booking;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;

class GuestDemographics extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map';
    protected static \UnitEnum|string|null $navigationGroup = 'Reports';
    protected static ?string $title = 'Guest Demographics';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.guest-demographics';

    public string $overviewPreset = 'this_month';
    public ?string $overviewStart = null; // Y-m-d
    public ?string $overviewEnd = null;   // Y-m-d

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->hasPrivilege('view_guest_demographics') ?? false;
    }

    public function mount(): void
    {
        $this->setOverviewPresetDefaults($this->overviewPreset);
        $this->form->fill([
            'overviewStart' => $this->overviewStart,
            'overviewEnd' => $this->overviewEnd,
        ]);
    }

    public function updatedOverviewPreset(string $value): void
    {
        $this->setOverviewPresetDefaults($value);
    }

    public function selectOverviewPreset(string $preset): void
    {
        $this->overviewPreset = $preset;
        $this->setOverviewPresetDefaults($preset);
    }

    public function updatedOverviewStart(): void
    {
        $this->overviewPreset = 'custom';
    }

    public function updatedOverviewEnd(): void
    {
        $this->overviewPreset = 'custom';
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'sm' => 2,
            ])
            ->components([
                DatePicker::make('overviewStart')
                    ->label('From')
                    ->required()
                    ->native(false)
                    ->closeOnDateSelection(true)
                    ->live(),
                DatePicker::make('overviewEnd')
                    ->label('To')
                    ->required()
                    ->native(false)
                    ->closeOnDateSelection(true)
                    ->live(),
            ]);
    }

    protected function getViewData(): array
    {
        // Overview report (calendar/presets)
        [$overviewStart, $overviewEnd] = $this->resolveOverviewRange();
        $overviewDemographics = $this->getHierarchicalData('successful', $overviewStart, $overviewEnd);
        $overviewLocalDemographics = $overviewDemographics->where('is_international', false);
        $overviewForeignDemographics = $overviewDemographics->where('is_international', true);

        $overviewLabel = $this->overviewLabel($overviewStart, $overviewEnd);
        $overviewRange = $overviewStart->format('M d, Y') . ' to ' . $overviewEnd->format('M d, Y');

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        return [
            'unpaid' => [
                'today' => $this->getTopLocation('unpaid', Carbon::today(), Carbon::today()),
                'next_7_days' => $this->getTopLocation('unpaid', Carbon::tomorrow(), Carbon::today()->addDays(7)),
                'this_month' => $this->getTopLocation('unpaid', $startOfMonth, $endOfMonth),
                'next_month' => $this->getTopLocation('unpaid', Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()),
            ],
            'successful' => [
                'today' => $this->getTopLocation('successful', Carbon::today(), Carbon::today()),
                'next_7_days' => $this->getTopLocation('successful', Carbon::tomorrow(), Carbon::today()->addDays(7)),
                'this_month' => $this->getTopLocation('successful', $startOfMonth, $endOfMonth),
                'next_month' => $this->getTopLocation('successful', Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()),
            ],

            // Raw data for printing complete hierarchy reports
            'reports' => [
                'unpaid' => [
                    'today' => $this->getHierarchicalData('unpaid', Carbon::today(), Carbon::today()),
                    'next_7_days' => $this->getHierarchicalData('unpaid', Carbon::tomorrow(), Carbon::today()->addDays(7)),
                    'this_month' => $this->getHierarchicalData('unpaid', $startOfMonth, $endOfMonth),
                    'next_month' => $this->getHierarchicalData('unpaid', Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()),
                    'all' => $this->getHierarchicalData('unpaid', Carbon::now()->subYears(10), Carbon::now()->addYears(10)), // all time approx
                ],
                'successful' => [
                    'today' => $this->getHierarchicalData('successful', Carbon::today(), Carbon::today()),
                    'next_7_days' => $this->getHierarchicalData('successful', Carbon::tomorrow(), Carbon::today()->addDays(7)),
                    'this_month' => $this->getHierarchicalData('successful', $startOfMonth, $endOfMonth),
                    'next_month' => $this->getHierarchicalData('successful', Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()),
                    'all' => $this->getHierarchicalData('successful', Carbon::now()->subYears(10), Carbon::now()->addYears(10)),
                ]
            ],

            'overviewLocalDemographics' => $overviewLocalDemographics,
            'overviewForeignDemographics' => $overviewForeignDemographics,
            'overviewLabel' => $overviewLabel,
            'overviewRange' => $overviewRange,
        ];
    }

    public function logReportDownload(string $type, ?string $period = null): void
    {
        $normalizedPeriod = $period === 'null' ? null : $period;

        ActivityLogger::log(
            category: 'report',
            event: 'report.downloaded',
            description: sprintf(
                'downloaded %s report%s.',
                str_replace('_', ' ', $type),
                $normalizedPeriod ? ' (' . str_replace('_', ' ', $normalizedPeriod) . ')' : '',
            ),
            meta: [
                'type' => $type,
                'period' => $normalizedPeriod,
            ],
        );
    }

    private function getHierarchicalData(string $kind, Carbon $startDate, Carbon $endDate)
    {
        return Booking::select(
            'guests.is_international',
            'guests.country',
            'guests.region',
            'guests.province',
            'guests.municipality',
            'guests.barangay',
            DB::raw('count(*) as total')
        )
            ->join('guests', 'bookings.guest_id', '=', 'guests.id')
            ->when($kind === 'unpaid', function ($query): void {
                $query->whereIn('bookings.payment_status', [
                    Booking::PAYMENT_STATUS_UNPAID,
                    Booking::PAYMENT_STATUS_PARTIAL,
                ]);
            })
            ->when($kind === 'successful', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('bookings.payment_status', Booking::PAYMENT_STATUS_PAID)
                        ->orWhereIn('bookings.booking_status', [
                            Booking::BOOKING_STATUS_OCCUPIED,
                            Booking::BOOKING_STATUS_COMPLETED,
                        ]);
                });
            })
            ->whereBetween('bookings.check_in', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->groupBy('guests.is_international', 'guests.country', 'guests.region', 'guests.province', 'guests.municipality', 'guests.barangay')
            ->orderByRaw("guests.is_international ASC, total DESC, guests.region ASC")
            ->get();
    }

    private function getTopLocation(string $kind, Carbon $startDate, Carbon $endDate): ?array
    {
        $topRegion = Booking::select('guests.region', DB::raw('count(*) as total'))
            ->join('guests', 'bookings.guest_id', '=', 'guests.id')
            ->when($kind === 'unpaid', function ($query): void {
                $query->whereIn('bookings.payment_status', [
                    Booking::PAYMENT_STATUS_UNPAID,
                    Booking::PAYMENT_STATUS_PARTIAL,
                ]);
            })
            ->when($kind === 'successful', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('bookings.payment_status', Booking::PAYMENT_STATUS_PAID)
                        ->orWhereIn('bookings.booking_status', [
                            Booking::BOOKING_STATUS_OCCUPIED,
                            Booking::BOOKING_STATUS_COMPLETED,
                        ]);
                });
            })
            ->whereBetween('bookings.check_in', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->whereNotNull('guests.region')
            ->where('guests.region', '!=', '')
            ->groupBy('guests.region')
            ->orderBy('total', 'desc')
            ->first();

        if (!$topRegion) {
            return null;
        }

        $topProvince = Booking::select('guests.province', DB::raw('count(*) as total'))
            ->join('guests', 'bookings.guest_id', '=', 'guests.id')
            ->when($kind === 'unpaid', function ($query): void {
                $query->whereIn('bookings.payment_status', [
                    Booking::PAYMENT_STATUS_UNPAID,
                    Booking::PAYMENT_STATUS_PARTIAL,
                ]);
            })
            ->when($kind === 'successful', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('bookings.payment_status', Booking::PAYMENT_STATUS_PAID)
                        ->orWhereIn('bookings.booking_status', [
                            Booking::BOOKING_STATUS_OCCUPIED,
                            Booking::BOOKING_STATUS_COMPLETED,
                        ]);
                });
            })
            ->whereBetween('bookings.check_in', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->where('guests.region', $topRegion->region)
            ->whereNotNull('guests.province')
            ->where('guests.province', '!=', '')
            ->groupBy('guests.province')
            ->orderBy('total', 'desc')
            ->first();

        return [
            'name' => $topRegion->region,
            'sub' => $topProvince ? $topProvince->province : null,
            'count' => $topRegion->total
        ];
    }

    public function viewBookingsAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('viewBookings')
            ->modalHeading(function (array $arguments) {
                $period = str_replace('_', ' ', $arguments['period'] ?? '');
                $type = $arguments['type'] ?? '';
                return 'Booking Details (' . ucwords($type) . ' - ' . ucwords($period) . ')';
            })
            ->modalContent(function (array $arguments) {
                $period = $arguments['period'] ?? 'today';
                $type = $arguments['type'] ?? 'unpaid';

                $dates = $this->getDateRangeForPeriod($period);

                $bookings = Booking::with('guest')
                    ->join('guests', 'bookings.guest_id', '=', 'guests.id')
                    ->select('bookings.*')
                    ->when($type === 'unpaid', function ($query): void {
                        $query->whereIn('bookings.payment_status', [
                            Booking::PAYMENT_STATUS_UNPAID,
                            Booking::PAYMENT_STATUS_PARTIAL,
                        ]);
                    })
                    ->when($type === 'successful', function ($query): void {
                        $query->where(function ($q): void {
                            $q->where('bookings.payment_status', Booking::PAYMENT_STATUS_PAID)
                                ->orWhereIn('bookings.booking_status', [
                                    Booking::BOOKING_STATUS_OCCUPIED,
                                    Booking::BOOKING_STATUS_COMPLETED,
                                ]);
                        });
                    })
                    ->whereBetween('bookings.check_in', [$dates[0]->startOfDay(), $dates[1]->endOfDay()])
                    ->orderByRaw("guests.region DESC, guests.province DESC, guests.municipality DESC, bookings.check_in ASC")
                    ->get();

                return new \Illuminate\Support\HtmlString(
                    view('filament.pages.demographics-details-modal', [
                        'bookings' => $bookings,
                    ])->render()
                );
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    private function getDateRangeForPeriod($period)
    {
        return match ($period) {
            'today' => [Carbon::today(), Carbon::today()],
            'next_7_days' => [Carbon::tomorrow(), Carbon::today()->addDays(7)],
            'this_month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'next_month' => [Carbon::now()->addMonth()->startOfMonth(), Carbon::now()->addMonth()->endOfMonth()],
            default => [Carbon::today(), Carbon::today()]
        };
    }

    private function setOverviewPresetDefaults(string $preset): void
    {
        $now = Carbon::now();

        if ($preset === 'custom') {
            if (! $this->overviewStart) {
                $this->overviewStart = $now->copy()->startOfMonth()->toDateString();
            }
            if (! $this->overviewEnd) {
                $this->overviewEnd = $now->copy()->endOfMonth()->toDateString();
            }
            return;
        }

        [$start, $end] = match ($preset) {
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        $this->overviewStart = $start->toDateString();
        $this->overviewEnd = $end->toDateString();
    }

    private function resolveOverviewRange(): array
    {
        $start = null;
        $end = null;

        if ($this->overviewStart) {
            $start = Carbon::parse($this->overviewStart);
        }
        if ($this->overviewEnd) {
            $end = Carbon::parse($this->overviewEnd);
        }

        $start ??= Carbon::now()->startOfMonth();
        $end ??= Carbon::now()->endOfMonth();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    private function overviewLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($start->copy()->startOfMonth()) && $end->isSameDay($start->copy()->endOfMonth())) {
            return 'Month: ' . $start->format('F Y');
        }

        if ($start->isSameDay($start->copy()->startOfYear()) && $end->isSameDay($start->copy()->endOfYear())) {
            return 'Year: ' . $start->format('Y');
        }

        return 'Dates: ' . $start->toDateString() . ' → ' . $end->toDateString();
    }
}
