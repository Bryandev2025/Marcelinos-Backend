<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ServicesStart extends Command
{
    protected $signature = 'services:start';

    protected $description = 'Start all long-running services (Reverb, queue worker, scheduler). Uses start-services.sh on Linux/macOS.';

    public function handle(): int
    {
        $script = base_path('start-services.sh');

        if (PHP_OS_FAMILY === 'Windows') {
            $bat = base_path('start-services.bat');
            if (is_file($bat)) {
                $this->info('Starting Reverb, Queue, and Scheduler...');
                passthru('"' . $bat . '"', $exitCode);
                return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
            }
            $this->warn('On Windows, run these in separate terminals (or run start-services.bat):');
            $this->line('  Terminal 1: php artisan reverb:start');
            $this->line('  Terminal 2: php artisan queue:work');
            $this->line('  Terminal 3: php artisan schedule:work');
            return self::SUCCESS;
        }

        if (!is_file($script)) {
            $this->error('start-services.sh not found in project root.');
            return self::FAILURE;
        }

        if (!is_executable($script)) {
            chmod($script, 0755);
        }

        passthru('bash ' . escapeshellarg($script), $exitCode);
        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
