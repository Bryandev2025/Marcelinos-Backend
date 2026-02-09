<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Venue extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $appends = [
        'featured_image_url',
        'gallery_urls',
    ];

    protected $fillable = ['name', 'description', 'capacity', 'price', 'status'];

    /* ================= STATUSES ================= */
    const STATUS_AVAILABLE = 'available';
    const STATUS_BOOKED = 'booked';
    const STATUS_MAINTENANCE = 'maintenance';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_BOOKED => 'Booked',
            self::STATUS_MAINTENANCE => 'Maintenance',
        ];
    }

    public static function statusColors(): array
    {
        return [
            'success' => self::STATUS_AVAILABLE,
            'danger' => self::STATUS_BOOKED,
            'secondary' => self::STATUS_MAINTENANCE,
        ];
    }

    /**
     * Define Media Collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured')
            ->singleFile();

        $this->addMediaCollection('gallery');
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('featured');

        return $media ? $this->resolveMediaUrl($media) : null;
    }

    public function getGalleryUrlsAttribute(): array
    {
        return $this->getMedia('gallery')
            ->map(fn (Media $media) => $this->resolveMediaUrl($media))
            ->values()
            ->all();
    }

    private function resolveMediaUrl(Media $media): string
    {
        $lifetime = (int) config('media-library.temporary_url_default_lifetime', 5);

        if ($media->disk === 's3') {
            return $media->getTemporaryUrl(now()->addMinutes($lifetime));
        }

        return $media->getUrl();
    }

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
     * Scope: only venues not booked by an active booking in the given date range.
     * Completed and cancelled bookings do not block availability.
     */
    public function scopeAvailableBetween($query, $checkIn, $checkOut)
    {
        return $query->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
            $q->whereIn('bookings.status', Booking::statusesThatBlockAvailability())
                ->where('bookings.check_in', '<', $checkOut)
                ->where('bookings.check_out', '>', $checkIn);
        });
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

}