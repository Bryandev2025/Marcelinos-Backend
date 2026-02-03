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

    /* ================= TYPES ================= */
    const TYPE_STANDARD = 'standard';
    const TYPE_FAMILY = 'family';
    const TYPE_DELUXE = 'deluxe';

    public static function typeOptions(): array
    {
        return [
            self::TYPE_STANDARD => 'Standard',
            self::TYPE_FAMILY => 'Family',
            self::TYPE_DELUXE => 'Deluxe',
        ];
    }

    /* ================= STATUSES ================= */
    const STATUS_AVAILABLE = 'available';
    const STATUS_OCCUPIED = 'occupied';
    const STATUS_CLEANING = 'cleaning';
    const STATUS_MAINTENANCE = 'maintenance';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_OCCUPIED => 'Occupied',
            self::STATUS_CLEANING => 'Cleaning',
            self::STATUS_MAINTENANCE => 'Maintenance',
        ];
    }

    public static function statusColors(): array
    {
        return [
            'success' => self::STATUS_AVAILABLE,
            'danger' => self::STATUS_OCCUPIED,
            'warning' => self::STATUS_CLEANING,
            'secondary' => self::STATUS_MAINTENANCE,
        ];
    }

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
            $q->where('bookings.status', '!=', Booking::STATUS_CANCELLED)
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