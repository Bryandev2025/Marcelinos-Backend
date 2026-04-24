<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ImportLegacyBookingsCsv extends Command
{
    protected $signature = 'bookings:import-legacy-csv
        {file : Absolute or relative CSV path}
        {--dry-run : Validate and preview without writing}
        {--delimiter=, : CSV delimiter character}
        {--default-status= : Optional default status when status column is missing}
        {--timezone=Asia/Manila : Timezone for date parsing and auto-status}
        {--allow-duplicates : Import rows even if they look already imported}
    ';

    protected $description = 'Import old bookings from a CSV file';

    /**
     * Header aliases accepted from legacy exports.
     *
     * @var array<string, array<int, string>>
     */
    private array $headerAliases = [
        'first_name' => ['first_name', 'guest_first_name', 'firstname', 'given_name'],
        'middle_name' => ['middle_name', 'guest_middle_name', 'middlename'],
        'last_name' => ['last_name', 'guest_last_name', 'lastname', 'surname'],
        'email' => ['email', 'guest_email', 'email_address'],
        'contact_num' => ['contact_num', 'contact_number', 'phone', 'mobile', 'guest_phone'],
        'check_in' => ['check_in', 'checkin', 'arrival', 'arrival_date', 'start_date'],
        'check_out' => ['check_out', 'checkout', 'departure', 'departure_date', 'end_date'],
        'total_price' => ['total_price', 'total', 'amount', 'amount_total', 'booking_total'],
        'status' => ['status', 'booking_status'],
        'payment_method' => ['payment_method', 'payment', 'payment_type'],
        'rooms' => ['rooms', 'room_names', 'room_name'],
        'venues' => ['venues', 'venue_names', 'venue_name'],
        'venue_event_type' => ['venue_event_type', 'event_type'],
    ];

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $path = $this->resolvePath($file);
        $dryRun = (bool) $this->option('dry-run');
        $delimiter = (string) $this->option('delimiter');
        $timezone = (string) $this->option('timezone');
        $defaultPair = $this->normalizeLegacyCsvToStatuses(trim((string) $this->option('default-status')));
        $allowDuplicates = (bool) $this->option('allow-duplicates');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("CSV file is not readable: {$path}");

            return self::FAILURE;
        }

        if (mb_strlen($delimiter) !== 1) {
            $this->error('The --delimiter option must be exactly one character.');

            return self::FAILURE;
        }

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $this->error("Invalid timezone: {$timezone}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->error("Unable to open file: {$path}");

            return self::FAILURE;
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        if (! is_array($headers) || count($headers) === 0) {
            fclose($handle);
            $this->error('The CSV file is empty or missing a header row.');

            return self::FAILURE;
        }

        $headerMap = $this->buildHeaderMap($headers);
        $missing = $this->missingRequiredHeaders($headerMap, ['first_name', 'last_name', 'email', 'contact_num', 'check_in', 'check_out', 'total_price']);
        if ($missing !== []) {
            fclose($handle);
            $this->error('Missing required CSV columns: '.implode(', ', $missing));
            $this->line('Accepted aliases are documented in this command source.');

            return self::FAILURE;
        }

        $rows = 0;
        $imported = 0;
        $skipped = 0;
        $duplicatesSkipped = 0;
        $errors = [];

        while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows++;
            if ($this->isEmptyCsvRow($line)) {
                $skipped++;
                continue;
            }

            try {
                $payload = $this->normalizeRow($line, $headerMap, $timezone, $defaultPair);
                if (! $allowDuplicates && $this->bookingAlreadyImported($payload)) {
                    $skipped++;
                    $duplicatesSkipped++;
                    continue;
                }

                if ($dryRun) {
                    $imported++;
                    continue;
                }

                DB::transaction(function () use ($payload): void {
                    $guest = $this->resolveGuest($payload);

                    Booking::withoutEvents(function () use ($guest, $payload): void {
                        $booking = Booking::create([
                            'guest_id' => $guest->id,
                            'reference_number' => $this->legacyReferenceNumber(),
                            'receipt_token' => (string) Str::uuid(),
                            'check_in' => $payload['check_in'],
                            'check_out' => $payload['check_out'],
                            'no_of_days' => $payload['no_of_days'],
                            'total_price' => $payload['total_price'],
                            'booking_status' => $payload['booking_status'],
                            'payment_status' => $payload['payment_status'],
                            'payment_method' => $payload['payment_method'],
                            'venue_event_type' => $payload['venue_event_type'],
                        ]);

                        $this->attachInventory($booking, $payload);
                    });
                });

                $imported++;
            } catch (Throwable $e) {
                $skipped++;
                $errors[] = "Row {$rows}: {$e->getMessage()}";
            }
        }

        fclose($handle);

        $this->info($dryRun ? 'Dry-run finished.' : 'Import finished.');
        $this->line("Rows read: {$rows}");
        $this->line("Imported: {$imported}");
        $this->line("Skipped: {$skipped}");
        if ($duplicatesSkipped > 0) {
            $this->line("Duplicates skipped: {$duplicatesSkipped}");
        }

        if ($errors !== []) {
            $this->newLine();
            $this->warn('Row errors:');
            foreach ($errors as $message) {
                $this->line("- {$message}");
            }
        }

        return $errors === [] ? self::SUCCESS : self::INVALID;
    }

    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\/\\\\]/', $file) === 1) {
            return $file;
        }

        return base_path($file);
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $idx => $header) {
            $normalizedHeader = $this->normalizeHeader((string) $header);
            if ($normalizedHeader !== '') {
                $map[$normalizedHeader] = $idx;
            }
        }

        $resolved = [];
        foreach ($this->headerAliases as $target => $aliases) {
            foreach ($aliases as $alias) {
                $normalizedAlias = $this->normalizeHeader($alias);
                if (array_key_exists($normalizedAlias, $map)) {
                    $resolved[$target] = $map[$normalizedAlias];
                    break;
                }
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, int>  $headerMap
     * @param  array<int, string>  $required
     * @return array<int, string>
     */
    private function missingRequiredHeaders(array $headerMap, array $required): array
    {
        $missing = [];
        foreach ($required as $key) {
            if (! array_key_exists($key, $headerMap)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['-', ' '], '_', $value);

        return preg_replace('/[^a-z0-9_]/', '', $value) ?? '';
    }

    /**
     * @param  array<int, string|null>  $line
     * @param  array<string, int>  $headerMap
     * @return array<string, mixed>
     */
    /**
     * @param  array{booking_status: string, payment_status: string}|null  $defaultPair
     */
    private function normalizeRow(array $line, array $headerMap, string $timezone, ?array $defaultPair): array
    {
        $firstName = $this->requiredCell($line, $headerMap['first_name'], 'first_name');
        $middleName = $this->optionalCell($line, $headerMap['middle_name'] ?? null);
        $lastName = $this->requiredCell($line, $headerMap['last_name'], 'last_name');
        $email = $this->requiredCell($line, $headerMap['email'], 'email');
        $contactNum = $this->requiredCell($line, $headerMap['contact_num'], 'contact_num');
        $checkIn = $this->parseDate($this->requiredCell($line, $headerMap['check_in'], 'check_in'), $timezone, 'check_in');
        $checkOut = $this->parseDate($this->requiredCell($line, $headerMap['check_out'], 'check_out'), $timezone, 'check_out');
        $totalPrice = $this->parseTotal($this->requiredCell($line, $headerMap['total_price'], 'total_price'));
        $statusRaw = $this->optionalCell($line, $headerMap['status'] ?? null);
        $paymentMethod = strtolower($this->optionalCell($line, $headerMap['payment_method'] ?? null) ?? 'cash');
        $paymentMethod = $paymentMethod === 'online' ? 'online' : 'cash';

        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            throw new \RuntimeException('check_out must be after check_in.');
        }

        $pair = $this->normalizeLegacyCsvToStatuses($statusRaw)
            ?? $defaultPair
            ?? $this->autoStatuses($checkIn, $checkOut, $timezone);
        if ($pair === null) {
            throw new \RuntimeException('Status is invalid.');
        }

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'email' => $email,
            'contact_num' => $contactNum,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'no_of_days' => max(1, $checkIn->diffInDays($checkOut)),
            'total_price' => $totalPrice,
            'booking_status' => $pair['booking_status'],
            'payment_status' => $pair['payment_status'],
            'payment_method' => $paymentMethod,
            'venue_event_type' => $this->optionalCell($line, $headerMap['venue_event_type'] ?? null),
            'rooms' => $this->parseNameList($this->optionalCell($line, $headerMap['rooms'] ?? null)),
            'venues' => $this->parseNameList($this->optionalCell($line, $headerMap['venues'] ?? null)),
        ];
    }

    /**
     * @param  array<int, string|null>  $line
     */
    private function requiredCell(array $line, int $index, string $field): string
    {
        $value = $this->optionalCell($line, $index);
        if ($value === null) {
            throw new \RuntimeException("{$field} is required.");
        }

        return $value;
    }

    /**
     * @param  array<int, string|null>  $line
     */
    private function optionalCell(array $line, ?int $index): ?string
    {
        if ($index === null || ! array_key_exists($index, $line)) {
            return null;
        }

        $value = trim((string) $line[$index]);

        return $value === '' ? null : $value;
    }

    private function parseDate(string $value, string $timezone, string $field): Carbon
    {
        $value = trim($value);
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'm/d/Y',
            'n/j/Y H:i:s',
            'n/j/Y H:i',
            'n/j/Y',
            'M j, Y H:i:s',
            'M j, Y H:i',
            'M j, Y',
            'F j, Y H:i:s',
            'F j, Y H:i',
            'F j, Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value, $timezone);
                if ($date !== false) {
                    if (! str_contains($format, 'H:i')) {
                        // Keep midnight if no time exists in CSV.
                        $date = $date->startOfDay();
                    }

                    return $date;
                }
            } catch (Throwable) {
                // Try next format.
            }
        }

        try {
            return Carbon::parse($value, $timezone);
        } catch (Throwable) {
            throw new \RuntimeException("{$field} has an invalid date value: {$value}");
        }
    }

    private function parseTotal(string $value): float
    {
        $normalized = str_replace([',', 'PHP', 'php', '₱', ' '], '', $value);
        if (! is_numeric($normalized)) {
            throw new \RuntimeException("Invalid total_price value: {$value}");
        }

        return max(0, round((float) $normalized, 2));
    }

    /**
     * @return array<int, string>
     */
    private function parseNameList(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        return collect(preg_split('/[|;]/', $value) ?: [])
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->values()
            ->all();
    }

    /**
     * Map a legacy single-column status (or empty for auto) to booking + payment columns (same rules as DB migration backfill).
     *
     * @return array{booking_status: string, payment_status: string}|null
     */
    private function normalizeLegacyCsvToStatuses(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtolower(trim($value));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return match ($normalized) {
            'unpaid' => [
                'booking_status' => Booking::BOOKING_STATUS_RESERVED,
                'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
            ],
            'partial' => [
                'booking_status' => Booking::BOOKING_STATUS_RESERVED,
                'payment_status' => Booking::PAYMENT_STATUS_PARTIAL,
            ],
            'paid', 'confirmed' => [
                'booking_status' => Booking::BOOKING_STATUS_RESERVED,
                'payment_status' => Booking::PAYMENT_STATUS_PAID,
            ],
            'occupied', 'checked_in' => [
                'booking_status' => Booking::BOOKING_STATUS_OCCUPIED,
                'payment_status' => Booking::PAYMENT_STATUS_PAID,
            ],
            'completed', 'checked_out' => [
                'booking_status' => Booking::BOOKING_STATUS_COMPLETED,
                'payment_status' => Booking::PAYMENT_STATUS_PAID,
            ],
            'cancelled', 'canceled' => [
                'booking_status' => Booking::BOOKING_STATUS_CANCELLED,
                'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
            ],
            'rescheduled' => [
                'booking_status' => Booking::BOOKING_STATUS_RESCHEDULED,
                'payment_status' => Booking::PAYMENT_STATUS_UNPAID,
            ],
            default => null,
        };
    }

    /**
     * @return array{booking_status: string, payment_status: string}
     */
    private function autoStatuses(Carbon $checkIn, Carbon $checkOut, string $timezone): array
    {
        $now = now($timezone);
        if ($checkOut->lessThanOrEqualTo($now)) {
            return [
                'booking_status' => Booking::BOOKING_STATUS_COMPLETED,
                'payment_status' => Booking::PAYMENT_STATUS_PAID,
            ];
        }

        if ($checkIn->lessThanOrEqualTo($now) && $checkOut->greaterThan($now)) {
            return [
                'booking_status' => Booking::BOOKING_STATUS_OCCUPIED,
                'payment_status' => Booking::PAYMENT_STATUS_PAID,
            ];
        }

        return [
            'booking_status' => Booking::BOOKING_STATUS_RESERVED,
            'payment_status' => Booking::PAYMENT_STATUS_PAID,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveGuest(array $payload): Guest
    {
        /** @var Guest|null $guest */
        $guest = Guest::query()
            ->where('email', $payload['email'])
            ->first();

        if ($guest) {
            return $guest;
        }

        return Guest::create([
            'first_name' => $payload['first_name'],
            'middle_name' => $payload['middle_name'],
            'last_name' => $payload['last_name'],
            'email' => $payload['email'],
            'contact_num' => $payload['contact_num'],
            'gender' => Guest::GENDER_OTHER,
            'is_international' => false,
            'country' => 'Philippines',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function bookingAlreadyImported(array $payload): bool
    {
        $email = (string) $payload['email'];
        $contact = (string) $payload['contact_num'];
        $firstName = (string) $payload['first_name'];
        $lastName = (string) $payload['last_name'];

        return Booking::query()
            ->where('check_in', $payload['check_in'])
            ->where('check_out', $payload['check_out'])
            ->where('total_price', $payload['total_price'])
            ->whereHas('guest', function ($query) use ($email, $contact, $firstName, $lastName): void {
                $query->where(function ($q) use ($email, $contact, $firstName, $lastName): void {
                    $q->where('email', $email)
                        ->orWhere(function ($q2) use ($contact, $firstName, $lastName): void {
                            $q2->where('contact_num', $contact)
                                ->where('first_name', $firstName)
                                ->where('last_name', $lastName);
                        });
                });
            })
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function attachInventory(Booking $booking, array $payload): void
    {
        $roomNames = is_array($payload['rooms'] ?? null) ? $payload['rooms'] : [];
        if ($roomNames !== []) {
            $rooms = Room::query()->whereIn('name', $roomNames)->get(['id', 'name']);
            $found = $rooms->pluck('name')->all();
            $missing = array_values(array_diff($roomNames, $found));
            if ($missing !== []) {
                throw new \RuntimeException('Room(s) not found: '.implode(', ', $missing));
            }

            $booking->rooms()->syncWithoutDetaching($rooms->pluck('id')->all());
        }

        $venueNames = is_array($payload['venues'] ?? null) ? $payload['venues'] : [];
        if ($venueNames !== []) {
            $venues = Venue::query()->whereIn('name', $venueNames)->get(['id', 'name']);
            $found = $venues->pluck('name')->all();
            $missing = array_values(array_diff($venueNames, $found));
            if ($missing !== []) {
                throw new \RuntimeException('Venue(s) not found: '.implode(', ', $missing));
            }

            $booking->venues()->syncWithoutDetaching($venues->pluck('id')->all());
        }
    }

    private function legacyReferenceNumber(): string
    {
        return 'LEGACY-'.now()->format('Y').'-'.strtoupper(Str::random(8));
    }

    /**
     * @param  array<int, string|null>  $line
     */
    private function isEmptyCsvRow(array $line): bool
    {
        foreach ($line as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
