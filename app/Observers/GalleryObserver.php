<?php

namespace App\Observers;

use App\Events\GalleryUpdated;
use App\Models\Gallery;
use Throwable;

/**
 * Broadcasts when gallery is created, updated, or deleted so frontend (homepage) refetches in real time.
 */
class GalleryObserver
{
    public function saved(Gallery $gallery): void
    {
        $this->dispatchGalleryUpdated();
    }

    public function deleted(Gallery $gallery): void
    {
        $this->dispatchGalleryUpdated();
    }

    private function dispatchGalleryUpdated(): void
    {
        try {
            GalleryUpdated::dispatch();
        } catch (Throwable $exception) {
            // Broadcasting issues should not block admin CRUD requests.
            report($exception);
        }
    }
}
