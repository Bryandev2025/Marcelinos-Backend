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
        return $this->hasMany(Booking::class);
    }

    // Removed the public function images() method because the Image model is gone.
    // Spatie uses $this->getMedia() instead.

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }
}