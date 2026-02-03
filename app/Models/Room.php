<?php

namespace App\Models;

use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Room extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = ['name', 'capacity', 'type', 'price', 'status'];

    /**
     * Define Media Collections
     * This tells Spatie how to handle your "Featured" vs "Gallery" logic.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured')
            ->singleFile(); // Ensures only one featured image exists

        $this->addMediaCollection('gallery'); // Allows multiple images
    }

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_room')->withTimestamps();
    }

    /**
     * Scope: only rooms not booked (by a non-cancelled booking) in the given date range.
     * Overlap: booking.check_in < $checkOut AND booking.check_out > $checkIn
     */
    public function scopeAvailableBetween($query, $checkIn, $checkOut)
    {
        return $query->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
            $q->where('bookings.status', '!=', 'cancelled')
                ->where('bookings.check_in', '<', $checkOut)
                ->where('bookings.check_out', '>', $checkIn);
        });
    }

    // Removed the public function images() method because the Image model is gone.
    // Spatie uses $this->getMedia() instead.

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
}