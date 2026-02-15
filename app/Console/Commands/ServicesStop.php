<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ServicesStop extends Command
{
    protected $signature = 'services:stop';

    protected $description = 'Stop services started by services:start (Reverb, queue worker, scheduler). Uses stop-services.sh on Linux/macOS.';

    public function handle(): int
    {
        $script = base_path('stop-services.sh');

        if (PHP_OS_FAMILY !== 'Linux' && PHP_OS_FAMILY !== 'Darwin') {
            $this->warn('On Windows, stop each process manually (Ctrl+C in each terminal).');
            return self::SUCCESS;
        }

        if (!is_file($script)) {
            $this->error('stop-services.sh not found in project root.');
            return self::FAILURE;
        }

        if (!is_executable($script)) {
            chmod($script, 0755);
        }

        passthru('bash ' . escapeshellarg($script), $exitCode);
        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
