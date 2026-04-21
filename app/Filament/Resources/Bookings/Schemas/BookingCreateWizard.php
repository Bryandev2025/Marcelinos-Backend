<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Filament\Forms\Components\PhAddressFields;
use App\Models\Booking;
use App\Models\BedSpecification;
use App\Models\Guest;
use App\Models\Room;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BookingCreateWizard
{
    /**
     * @return array<int, Step>
     */
    public static function steps(): array
    {
        return [
            Step::make('Accommodation')
                ->description('Pick dates, choose bed specification, then select available room(s) for those dates.')
                ->schema([
                    DateTimePicker::make('check_in')
                        ->label('Check-in')
                        ->required()
                        ->default(fn () => now()->startOfDay()->addHours(12))
                        ->native(false)
                        ->live(onBlur: true)
                        ->seconds(false)
                        ->minDate(now()->startOfDay())
                        ->disabledDates(fn (Get $get): array => BookingForm::disabledCalendarDateStringsForWizard([]))
                        ->helperText('Blocked days (maintenance / closed) show in red on the calendar and cannot be picked.')
                        ->rules([
                            fn (Get $get) => self::roomAvailabilityRuleForCheckIn($get),
                        ])
                        ->afterStateUpdated(fn (Get $get, Set $set) => BookingForm::updatePricing($get, $set)),

                    DateTimePicker::make('check_out')
                        ->label('Check-out')
                        ->required()
                        ->default(fn () => now()->startOfDay()->addDay()->addHours(10))
                        ->native(false)
                        ->live(onBlur: true)
                        ->seconds(false)
                        ->disabledDates(function (Get $get): array {
                            $disabled = BookingForm::disabledCalendarDateStringsForWizard([]);

                            $checkIn = $get('check_in');
                            if (filled($checkIn)) {
                                try {
                                    $disabled[] = Carbon::parse($checkIn)->format('Y-m-d');
                                } catch (\Exception $e) {
                                    // ignore invalid date
                                }
                            }

                            return array_values(array_unique($disabled));
                        })
                        ->helperText('Same blocked days as check-in; range must avoid all blocked nights.')
                        ->minDate(fn (Get $get) => filled($get('check_in'))
                            ? Carbon::parse($get('check_in'))->startOfDay()->addDay()
                            : now())
                        ->rules([
                            fn (Get $get) => function (string $attribute, $value, $fail) use ($get): void {
                                $checkIn = $get('check_in');
                                if (! $checkIn || ! $value) {
                                    return;
                                }
                                try {
                                    $start = Carbon::parse($checkIn);
                                    $end = Carbon::parse($value);
                                } catch (\Exception $e) {
                                    return;
                                }
                                if ($end->lessThanOrEqualTo($start) || $end->isSameDay($start)) {
                                    $fail('Check-out must be at least the next day after check-in.');
                                }
                            },
                            fn (Get $get) => self::roomAvailabilityRuleForCheckOut($get),
                        ])
                        ->afterStateUpdated(fn (Get $get, Set $set) => BookingForm::updatePricing($get, $set)),

                    Select::make('bed_specification_id')
                        ->label('Bed specification')
                        ->options(fn (): array => BedSpecification::query()->orderBy('specification')->pluck('specification', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->helperText('Choose the bed specification first. The Rooms list will show only rooms with this spec that are available for the selected dates.')
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            $set('rooms', []);
                            BookingForm::updatePricing($get, $set);
                        })
                        ->columnSpanFull(),

                    Select::make('rooms')
                        ->label('Rooms')
                        ->relationship(
                            'rooms',
                            'name',
                            modifyQueryUsing: function ($query, ?string $search, ?Booking $record, Get $get): void {
                                $checkIn = $get('check_in');
                                $checkOut = $get('check_out');
                                $bedSpecId = $get('bed_specification_id');
                                if (! $checkIn || ! $checkOut || ! $bedSpecId) {
                                    $query->whereRaw('0 = 1');
                                    return;
                                }
                                try {
                                    $start = Carbon::parse((string) $checkIn);
                                    $end = Carbon::parse((string) $checkOut);
                                } catch (\Exception $e) {
                                    $query->whereRaw('0 = 1');
                                    return;
                                }
                                if ($end->lessThanOrEqualTo($start)) {
                                    $query->whereRaw('0 = 1');
                                    return;
                                }

                                $typeCol = $query->getModel()->qualifyColumn('type');
                                $nameCol = $query->getModel()->qualifyColumn('name');
                                $query->availableBetween($start, $end, null)
                                    ->whereHas('bedSpecifications', fn ($q) => $q->where('bed_specifications.id', (int) $bedSpecId))
                                    ->with(['bedSpecifications'])
                                    ->orderBy($typeCol)
                                    ->orderBy($nameCol);
                            },
                        )
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->helperText('Rooms are filtered by the selected bed specification and availability for the selected dates.')
                        ->rules([
                            fn (Get $get, ?Booking $record) => function (string $attribute, $value, $fail) use ($get, $record): void {
                                if (BookingForm::hasRoomConflicts($value, $get('check_in'), $get('check_out'), $record)) {
                                    $fail('One or more selected rooms are not available for the chosen dates.');
                                }
                            },
                        ])
                        ->afterStateUpdated(fn (Get $get, Set $set) => BookingForm::updatePricing($get, $set))
                        ->columnSpanFull(),

                    TextInput::make('no_of_days')
                        ->label('Nights')
                        ->numeric()
                        ->suffix('nights')
                        ->readOnly()
                        ->dehydrated(),

                    TextInput::make('total_price')
                        ->label('Room total (estimated)')
                        ->default(0)
                        ->readOnly()
                        ->dehydrated()
                        ->numeric()
                        ->prefix('₱')
                        ->helperText('Rooms × nights. Additional payment step records what the guest pays now.'),
                ]),
            Step::make('Guest details')
                ->description('Create the guest profile for this booking.')
                ->schema([
                    TextInput::make('first_name')
                        ->required()
                        ->live(onBlur: true)
                        ->extraInputAttributes(['class' => 'uppercase'])
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('first_name', Str::upper(trim((string) $state))))
                        ->dehydrateStateUsing(fn (?string $state): string => Str::upper(trim((string) $state)))
                        ->maxLength(100),
                    TextInput::make('middle_name')
                        ->live(onBlur: true)
                        ->extraInputAttributes(['class' => 'uppercase'])
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('middle_name', Str::upper(trim((string) $state))))
                        ->dehydrateStateUsing(fn (?string $state): string => Str::upper(trim((string) $state)))
                        ->maxLength(100),
                    TextInput::make('last_name')
                        ->required()
                        ->live(onBlur: true)
                        ->extraInputAttributes(['class' => 'uppercase'])
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('last_name', Str::upper(trim((string) $state))))
                        ->dehydrateStateUsing(fn (?string $state): string => Str::upper(trim((string) $state)))
                        ->maxLength(100),
                    Select::make('gender')
                        ->options(Guest::genderOptions())
                        ->required()
                        ->native(false),
                    TextInput::make('contact_num')
                        ->label('Phone number')
                        ->required()
                        ->maxLength(20),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->helperText('Will be used to send booking confirmation and other notifications. You may reuse an email from a previous guest (same person or family).')
                        ->maxLength(255),
                    Toggle::make('is_international')
                        ->label('Foreign / international address')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state): void {
                            if ($state) {
                                $set('ph_region_code', null);
                                $set('ph_province_code', null);
                                $set('ph_municipality_code', null);
                                $set('ph_barangay_code', null);
                                $set('region', null);
                                $set('province', null);
                                $set('municipality', null);
                                $set('barangay', null);
                            } else {
                                $set('country', 'Philippines');
                            }
                        }),
                    TextInput::make('country')
                        ->default('Philippines')
                        ->maxLength(100)
                        ->required(fn (Get $get) => (bool) $get('is_international'))
                        ->visible(fn (Get $get) => (bool) $get('is_international')),
                    ...PhAddressFields::make(),
                ]),
            Step::make('Review')
                ->description('Confirm stay and guest details before payment. Use the step tabs above to go back and edit.')
                ->schema([
                    Section::make('Review summary')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->iconColor('primary')
                        ->description('Check dates, rooms, and guest contact — this is what you are about to book.')
                        ->schema([
                            Text::make('Please confirm the details below')
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large)
                                ->color('primary'),
                            Section::make('Stay')
                                ->icon('heroicon-o-home')
                                ->iconColor('primary')
                                ->compact()
                                ->schema([
                                    Text::make(fn (Get $get): string => self::formatCheckInOut($get))
                                        ->weight(FontWeight::SemiBold)
                                        ->size(TextSize::Medium),
                                    Text::make(fn (Get $get): string => self::formatRoomsLine($get)),
                                    Text::make(fn (Get $get): string => self::formatNightsAndTotal($get))
                                        ->weight(FontWeight::SemiBold),
                                ]),
                            Section::make('Guest')
                                ->icon('heroicon-o-user')
                                ->iconColor('primary')
                                ->compact()
                                ->schema([
                                    Text::make(fn (Get $get): string => self::formatGuestName($get))
                                        ->weight(FontWeight::SemiBold)
                                        ->size(TextSize::Medium),
                                    Text::make(function (Get $get): string {
                                        $g = (string) $get('gender');

                                        return 'Gender: '.(Guest::genderOptions()[$g] ?? '—');
                                    }),
                                    Text::make(fn (Get $get): string => 'Phone: '.($get('contact_num') ?: '—')),
                                    Text::make(fn (Get $get): string => 'Email: '.($get('email') ?: '—')),
                                    Text::make(fn (Get $get): string => self::formatAddress($get)),
                                ]),
                        ]),
                ]),
            Step::make('Payment')
                ->description('Record what the guest pays now: full balance or any custom amount.')
                ->schema([
                    Radio::make('admin_payment_mode')
                        ->label('Payment')
                        ->options([
                            'full' => 'Pay full amount (matches booking total)',
                            'custom' => 'Custom amount (partial deposit or other)',
                        ])
                        ->default('full')
                        ->live()
                        ->required(),
                    TextInput::make('admin_payment_amount')
                        ->label('Amount to record')
                        ->helperText('Only when using a custom amount. Whole pesos; cannot exceed the booking total.')
                        ->numeric()
                        ->prefix('₱')
                        ->minValue(0)
                        ->maxValue(fn (Get $get) => max(0, (int) ceil((float) ($get('total_price') ?? 0))))
                        ->visible(fn (Get $get) => $get('admin_payment_mode') === 'custom')
                        ->required(fn (Get $get) => $get('admin_payment_mode') === 'custom')
                        ->dehydrated(fn (Get $get) => $get('admin_payment_mode') === 'custom'),
                ]),
            Step::make('Confirmation')
                ->description('Everything below will be saved when you create the booking.')
                ->schema([
                    Text::make(fn (Get $get): string => 'Stay: '.trim(self::formatCheckInOut($get).' · '.self::formatRoomsLine($get).' · '.self::formatNightsAndTotal($get)))
                        ->weight(FontWeight::Bold),
                    Text::make(fn (Get $get): string => 'Guest: '.self::formatGuestSummary($get)),
                    Text::make(fn (Get $get): string => 'Payment to record now: '.self::formatPaymentLine($get))
                        ->weight(FontWeight::SemiBold),
                    Text::make('When you are satisfied, click Create below to finalize. The guest receives the usual booking email when the address is valid.')
                        ->color('neutral'),
                ]),
        ];
    }

    private static function formatCheckInOut(Get $get): string
    {
        $in = $get('check_in');
        $out = $get('check_out');
        try {
            $inF = $in ? Carbon::parse($in)->format('M j, Y g:i A') : '—';
            $outF = $out ? Carbon::parse($out)->format('M j, Y g:i A') : '—';
        } catch (\Exception $e) {
            return 'Check-in / check-out: —';
        }

        return "Check-in: {$inF} → Check-out: {$outF}";
    }

    private static function formatRoomsLine(Get $get): string
    {
        $ids = $get('rooms') ?? [];
        $ids = is_array($ids) ? array_filter($ids) : [];
        if ($ids === []) {
            return 'Rooms: —';
        }
        $names = Room::query()->whereIn('id', $ids)->pluck('name')->sort()->values()->all();

        return 'Rooms: '.(empty($names) ? '—' : implode(', ', $names));
    }

    private static function formatNightsAndTotal(Get $get): string
    {
        $nights = (int) ($get('no_of_days') ?? 0);
        $total = number_format((float) ($get('total_price') ?? 0), 2);

        return "Nights: {$nights} · Total: ₱{$total}";
    }

    private static function formatGuestName(Get $get): string
    {
        $first = trim((string) $get('first_name'));
        $middle = trim((string) $get('middle_name'));
        $last = trim((string) $get('last_name'));
        $mid = $middle !== '' ? " {$middle} " : ' ';

        return "Name: {$first}{$mid}{$last}";
    }

    private static function formatAddress(Get $get): string
    {
        if ($get('is_international')) {
            $country = $get('country') ?: '—';

            return "Address (international): {$country}";
        }

        $parts = array_filter([
            $get('region'),
            $get('province'),
            $get('municipality'),
            $get('barangay'),
        ]);

        return 'Address: '.($parts === [] ? '—' : implode(' · ', $parts));
    }

    private static function formatGuestSummary(Get $get): string
    {
        return trim(self::formatGuestName($get).' · '.self::formatAddress($get).' · '.($get('email') ?: '—'));
    }

    private static function formatPaymentLine(Get $get): string
    {
        $total = (float) ($get('total_price') ?? 0);
        $mode = $get('admin_payment_mode');
        if ($mode === 'custom') {
            $amt = (float) ($get('admin_payment_amount') ?? 0);
        } else {
            $amt = $total;
        }

        $amtStr = number_format(max(0, $amt), 2);
        $totalStr = number_format($total, 2);

        return "₱{$amtStr}".($mode === 'full' ? " (full balance of ₱{$totalStr})" : " (custom; booking total ₱{$totalStr})");
    }

    /**
     * Re-validate room conflicts when check-in changes (rooms rule alone does not re-run).
     *
     * @return Closure(string, mixed, Closure): void
     */
    private static function roomAvailabilityRuleForCheckIn(Get $get): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($get): void {
            if (! $value || ! $get('check_out')) {
                return;
            }
            if (BookingForm::hasRoomConflicts($get('rooms'), $value, $get('check_out'), null)) {
                $fail('Selected room(s) are not available for these dates (another booking or block overlaps).');
            }
        };
    }

    /**
     * @return Closure(string, mixed, Closure): void
     */
    private static function roomAvailabilityRuleForCheckOut(Get $get): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($get): void {
            if (! $get('check_in') || ! $value) {
                return;
            }
            if (BookingForm::hasRoomConflicts($get('rooms'), $get('check_in'), $value, null)) {
                $fail('Selected room(s) are not available for these dates (another booking or block overlaps).');
            }
        };
    }
}
