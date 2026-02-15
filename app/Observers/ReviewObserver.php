<?php

namespace App\Observers;

use App\Events\ReviewsUpdated;
use App\Models\Review;

/**
 * Broadcasts when a review/testimonial is created, updated, or deleted so frontend (landing) refetches in real time.
 */
class ReviewObserver
{
    public function saved(Review $review): void
    {
        ReviewsUpdated::dispatch();
    }

    public function deleted(Review $review): void
    {
        ReviewsUpdated::dispatch();
    }
}
