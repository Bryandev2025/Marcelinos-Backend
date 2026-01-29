<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Venue extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = ['name', 'description', 'capacity', 'price'];

    // General collection of images
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_venue')->withTimestamps();
    }

    /**
     * Scope: only venues not booked (by a non-cancelled booking) in the given date range.
     */
    public function scopeAvailableBetween($query, $checkIn, $checkOut)
    {
        return $query->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
            $q->where('bookings.status', '!=', 'cancelled')
                ->where('bookings.check_in', '<', $checkOut)
                ->where('bookings.check_out', '>', $checkIn);
        });
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }

}