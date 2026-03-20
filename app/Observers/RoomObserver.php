<?php

namespace App\Observers;

use App\Events\RoomsUpdated;
use App\Models\Room;

class RoomObserver
{
    public function saved(Room $room): void
    {
        $this->safeBroadcast();

        // Only log if an authenticated user with staff/admin role is present
        $user = auth()->user();
        if ($user && in_array($user->role, ['admin', 'staff'])) {
            $oldMedia = $room->getOriginal('featured_image');
            $newMedia = $room->getFirstMedia('featured');
            $hadPhoto = $oldMedia || $room->getMedia('featured')->count() > 0;
            $hasPhoto = $newMedia !== null;

            if ($hasPhoto && !$hadPhoto) {
                \App\Support\ActivityLogger::log(
                    category: 'room',
                    event: 'photo.uploaded',
                    description: sprintf('Room photo uploaded for "%s".', $room->name),
                    subject: $room,
                    meta: ['room_id' => $room->id, 'action' => 'uploaded'],
                    userId: $user->id,
                );
            } elseif ($hasPhoto && $hadPhoto) {
                \App\Support\ActivityLogger::log(
                    category: 'room',
                    event: 'photo.replaced',
                    description: sprintf('Room photo replaced for "%s".', $room->name),
                    subject: $room,
                    meta: ['room_id' => $room->id, 'action' => 'replaced'],
                    userId: $user->id,
                );
            }
        }
    }

    public function deleted(Room $room): void
    {
        $this->safeBroadcast();
    }

    private function safeBroadcast(): void
    {
        try {
            RoomsUpdated::dispatch();
        } catch (\Throwable $exception) {
            file_put_contents(
                storage_path('logs/laravel.log'),
                now()->toDateTimeString() . ' RoomsUpdated broadcast failed: ' . $exception->getMessage() . "\n",
                FILE_APPEND
            );
        }
    }
}