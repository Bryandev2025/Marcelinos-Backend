<?php

namespace App\Observers;

use App\Events\GalleryUpdated;
use App\Models\Gallery;

/**
 * Broadcasts when gallery changes so frontend (homepage) stays up to date.
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
