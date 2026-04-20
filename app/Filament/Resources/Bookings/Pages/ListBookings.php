<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use JeffersonGoncalves\Filament\QrCodeField\Forms\Components\QrCodeInput;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    public function getHeading(): string
    {
        return 'Bookings list';
    }

    public function getSubheading(): ?string
    {
        return 'Search, filter, and manage reservations in one place.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendarView')
                ->label('Booking Calendar')
                ->color('gray')
                ->url(BookingResource::getUrl('roomCalendar')),
            Action::make('importLegacyCsv')
                ->label('Import Old Bookings')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->modalHeading('Import Old Bookings')
                ->modalDescription('Step 1: Upload your CSV file. Step 2: Keep "Check file only" turned on and click import. Step 3: If results look correct, turn it off and import again to save.')
                ->form([
                    Placeholder::make('template_path')
                        ->label('CSV Template')
                        ->content('Copy this template format: `storage/app/examples/legacy-bookings-template.csv`.'),
                    FileUpload::make('csv_file')
                        ->label('CSV File')
                        ->disk('local')
                        ->directory('imports/legacy-bookings')
                        ->storeFiles(false)
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                        ])
                        ->required(),
                    Toggle::make('dry_run')
                        ->label('Check file only (do not save yet)')
                        ->default(true),
                    Toggle::make('allow_duplicates')
                        ->label('Import even if booking may already exist')
                        ->helperText('Keep this OFF in normal use to avoid duplicate bookings.')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $uploaded = $data['csv_file'] ?? null;
                    $absolutePath = null;

                    if ($uploaded instanceof TemporaryUploadedFile) {
                        $absolutePath = $uploaded->getRealPath();
                    } elseif (is_string($uploaded) && trim($uploaded) !== '') {
                        $candidate = storage_path('app/'.$uploaded);
                        $absolutePath = is_file($candidate) ? $candidate : $uploaded;
                    }

                    if (! is_string($absolutePath) || trim($absolutePath) === '' || ! is_readable($absolutePath)) {
                        Notification::make()
                            ->title('Uploaded CSV file is not readable.')
                            ->body('Please upload the file again and retry import.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $dryRun = (bool) ($data['dry_run'] ?? true);
                    $allowDuplicates = (bool) ($data['allow_duplicates'] ?? false);

                    $exitCode = Artisan::call('bookings:import-legacy-csv', [
                        'file' => $absolutePath,
                        '--dry-run' => $dryRun,
                        '--allow-duplicates' => $allowDuplicates,
                    ]);

                    $output = trim(Artisan::output());
                    $notification = Notification::make()
                        ->title($exitCode === 0 ? 'Legacy CSV processed.' : 'Legacy CSV import failed.')
                        ->body($output !== '' ? $output : 'No command output.');

                    if ($exitCode === 0) {
                        $notification->success()->send();
                    } else {
                        $notification->danger()->send();
                    }
                }),
            CreateAction::make(),
            Action::make('scanQr')
                ->label('Scan QR')
                ->icon('heroicon-o-qr-code')
                ->color('primary')
                ->modalHeading('Scan Booking QR Code')
                ->modalDescription('Open your camera and hold the guest\'s booking QR code within the frame to look up their reservation instantly.')
                ->modalWidth('md')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->form([
                    QrCodeInput::make('qr_payload')
                        ->hiddenLabel()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?string $state, $livewire): void {
                            $payload = $state;

                            if (! $payload) {
                                Notification::make()
                                    ->title('No QR code data found.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            [$bookingId, $reference] = self::extractBookingLookupFromQr($payload);

                            $booking = null;

                            if ($bookingId) {
                                $booking = Booking::find($bookingId);
                            }

                            if (! $booking && $reference) {
                                $booking = Booking::where('reference_number', $reference)->first();
                            }

                            if (! $booking) {
                                Notification::make()
                                    ->title('Booking not found.')
                                    ->body('The scanned QR code did not match any booking. Please try again.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $livewire->redirect(BookingResource::getUrl('view', ['record' => $booking]));
                        }),
                ])
                ->action(fn() => null),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All')
                ->badge(Booking::query()->count()),
        ];

        $statusOrder = [
            Booking::STATUS_UNPAID,
            Booking::STATUS_PARTIAL,
            Booking::STATUS_PAID,
            Booking::STATUS_OCCUPIED,
            Booking::STATUS_COMPLETED,
            Booking::STATUS_CANCELLED,
            Booking::STATUS_RESCHEDULED,
        ];

        $statusOptions = Booking::statusOptions();

        foreach ($statusOrder as $status) {
            if (! array_key_exists($status, $statusOptions)) {
                continue;
            }

            $tabs[$status] = Tab::make($statusOptions[$status])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status))
                ->badge(Booking::query()->where('status', $status)->count());
        }

        return $tabs;
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    private static function extractBookingLookupFromQr(string $payload): array
    {
        $cleanPayload = trim($payload);
        $cleanPayload = preg_replace('/^\xEF\xBB\xBF/', '', $cleanPayload) ?? $cleanPayload;

        $decoded = json_decode($cleanPayload, true);

        if (! is_array($decoded)) {
            // Some QR generators / decoders wrap JSON as a JSON string (e.g. "\"{...}\"").
            if (is_string($decoded)) {
                $inner = json_decode($decoded, true);
                if (is_array($inner)) {
                    $decoded = $inner;
                }
            }
        }

        if (! is_array($decoded)) {
            $base64Decoded = base64_decode($cleanPayload, true);
            if (is_string($base64Decoded) && $base64Decoded !== '') {
                $decoded = json_decode($base64Decoded, true);
            }
        }

        $bookingId = null;
        $reference = null;

        if (is_array($decoded)) {
            $bookingId = $decoded['booking_id'] ?? $decoded['bookingId'] ?? $decoded['id'] ?? null;
            $reference = $decoded['reference_number']
                ?? $decoded['reference']
                ?? $decoded['referenceNumber']
                ?? $decoded['ref']
                ?? null;
        }

        if (is_numeric($bookingId)) {
            $bookingId = (int) $bookingId;
        } else {
            $bookingId = null;
        }

        if (! is_string($reference) || trim($reference) === '') {
            $reference = self::extractReferenceFromUrlOrText($cleanPayload);
        } else {
            $reference = trim($reference);
        }

        return [$bookingId, $reference];
    }

    private static function extractReferenceFromUrlOrText(string $payload): ?string
    {
        $query = parse_url($payload, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            foreach (['reference', 'reference_number', 'ref'] as $key) {
                $value = $params[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        if (preg_match('/\bMWA-\d{4}-\d{6}\b/', $payload, $matches) === 1) {
            return $matches[0];
        }

        if (! str_starts_with($payload, '{') && ! str_starts_with($payload, '[')) {
            $trimmed = trim($payload);
            return $trimmed !== '' ? $trimmed : null;
        }

        return null;
    }
}
