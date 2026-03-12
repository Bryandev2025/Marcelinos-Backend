<?php

namespace App\Observers;

use App\Events\RoomsUpdated;
use App\Models\Room;
use Illuminate\Support\Facades\Log;

/**
 * Broadcasts so frontend stays up to date in real time.
 * Fires on create, update, and delete so the client refetches rooms (Step1, homepage).
 */
class RoomObserver
{
    public function saved(Room $room): void
    {
        $this->broadcastRoomsUpdated();
    }

    public function deleted(Room $room): void
    {
        $this->broadcastRoomsUpdated();
    }

    private function broadcastRoomsUpdated(): void
    {
        if ($this->shouldSkipBroadcast()) {
            return;
        }

        try {
            RoomsUpdated::dispatch();
        } catch (\Throwable $exception) {
            Log::warning('RoomsUpdated broadcast failed', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function shouldSkipBroadcast(): bool
    {
        $connection = (string) config('broadcasting.default');

        if ($connection !== 'reverb') {
            return false;
        }

        $host = strtolower((string) data_get(config('broadcasting.connections.reverb'), 'options.host', ''));

        if (! app()->environment('production')) {
            return false;
        }

        if (! in_array($host, ['localhost', '127.0.0.1'], true)) {
            return false;
        }

        Log::warning('Skipping RoomsUpdated broadcast: invalid Reverb host for production.', [
            'host' => $host,
            'connection' => $connection,
        ]);

        return true;
    }
}
