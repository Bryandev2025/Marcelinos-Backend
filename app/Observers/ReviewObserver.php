<?php

namespace App\Observers;

use App\Events\ReviewsUpdated;
use App\Models\Review;

/**
 * Broadcasts when a review/testimonial is added or updated so frontend (landing) stays up to date.
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
