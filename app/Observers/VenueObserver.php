<?php

namespace App\Observers;

use App\Events\VenuesUpdated;
use App\Models\Venue;
use Illuminate\Support\Facades\Log;

class VenueObserver
{
    public function saved(Venue $venue): void
    {
        $this->safeBroadcast();
        $this->safeBroadcast();

        // Only log if an authenticated user with staff/admin role is present
        $user = auth()->user();
        if ($user && in_array($user->role, ['admin', 'staff'])) {
            $oldMedia = $venue->getOriginal('featured_image');
            $newMedia = $venue->getFirstMedia('featured');
            $hadPhoto = $oldMedia || $venue->getMedia('featured')->count() > 0;
            $hasPhoto = $newMedia !== null;

            if ($hasPhoto && !$hadPhoto) {
                \App\Support\ActivityLogger::log(
                    category: 'venue',
                    event: 'photo.uploaded',
                    description: sprintf('Venue photo uploaded for "%s".', $venue->name),
                    subject: $venue,
                    meta: ['venue_id' => $venue->id, 'action' => 'uploaded'],
                    userId: $user->id,
                );
            } elseif ($hasPhoto && $hadPhoto) {
                \App\Support\ActivityLogger::log(
                    category: 'venue',
                    event: 'photo.replaced',
                    description: sprintf('Venue photo replaced for "%s".', $venue->name),
                    subject: $venue,
                    meta: ['venue_id' => $venue->id, 'action' => 'replaced'],
                    userId: $user->id,
                );
            }
        }
    }

    public function deleted(Venue $venue): void
    {
        $this->safeBroadcast();
    }

    private function safeBroadcast(): void
    {
        try {
            VenuesUpdated::dispatch();
        } catch (\Throwable $exception) {
            $message = trim($exception->getMessage());

            // Prevent huge HTML 404 pages from flooding logs.
            if (str_contains($message, '<!DOCTYPE html>')) {
                $message = 'Received HTML error page instead of broadcast response (likely wrong Pusher endpoint).';
            }

            Log::warning('VenuesUpdated broadcast failed', [
                'error' => $message,
                'exception' => get_class($exception),
            ]);
        }
    }
}