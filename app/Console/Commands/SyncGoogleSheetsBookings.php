<?php

namespace App\Console\Commands;

use App\Services\GoogleSheetsBookingSyncService;
use Illuminate\Console\Command;

class SyncGoogleSheetsBookings extends Command
{
    protected $signature = 'bookings:sync-google-sheet';

    protected $description = 'Rebuild Google Sheets tabs from all bookings in the database';

    public function handle(GoogleSheetsBookingSyncService $syncService): int
    {
        $this->info('Starting full Google Sheets sync from database...');

        $syncService->syncAllBookings();

        $this->info('Google Sheets full sync completed.');

        return self::SUCCESS;
    }
}
