<?php

namespace App\Observers;

use App\Events\GalleryUpdated;
use App\Models\Gallery;

/**
 * Broadcasts when gallery is created, updated, or deleted so frontend (homepage) refetches in real time.
 */
class GalleryObserver
{
    public function saved(Gallery $gallery): void
    {
        GalleryUpdated::dispatch();
    }

    public function deleted(Gallery $gallery): void
    {
        GalleryUpdated::dispatch();
    }
}
