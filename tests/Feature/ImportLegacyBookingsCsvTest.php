<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportLegacyBookingsCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_legacy_bookings_from_csv_and_auto_sets_completed_status(): void
    {
        $csvPath = storage_path('framework/testing/legacy-bookings.csv');
        if (! is_dir(dirname($csvPath))) {
            mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode(PHP_EOL, [
            'first_name,last_name,email,contact_num,check_in,check_out,total_price',
            'Juan,Dela Cruz,juan@example.com,09171234567,2025-02-01,2025-02-03,3500.00',
        ]));

        $this->artisan('bookings:import-legacy-csv', ['file' => $csvPath])
            ->assertExitCode(0)
            ->expectsOutputToContain('Imported: 1')
            ->expectsOutputToContain('Skipped: 0');

        $booking = Booking::query()
            ->with('guest')
            ->whereHas('guest', fn ($q) => $q->where('email', 'juan@example.com'))
            ->first();
        $this->assertNotNull($booking);
        $this->assertSame(Booking::BOOKING_STATUS_COMPLETED, $booking->booking_status);
        $this->assertSame(Booking::PAYMENT_STATUS_PAID, $booking->payment_status);
        $this->assertSame('juan@example.com', $booking->guest?->email);
        $this->assertSame('cash', $booking->payment_method);
    }

    public function test_dry_run_does_not_write_rows(): void
    {
        $csvPath = storage_path('framework/testing/legacy-bookings-dry-run.csv');
        if (! is_dir(dirname($csvPath))) {
            mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode(PHP_EOL, [
            'first_name,last_name,email,contact_num,check_in,check_out,total_price',
            'Maria,Santos,maria@example.com,09179876543,2025-03-10,2025-03-12,4200.00',
        ]));

        $this->artisan('bookings:import-legacy-csv', ['file' => $csvPath, '--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Dry-run finished.')
            ->expectsOutputToContain('Imported: 1');

        $this->assertDatabaseMissing('guests', ['email' => 'maria@example.com']);
    }
}
