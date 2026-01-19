<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

class Room extends Model
{
   protected $guarded = [];

    /**
     * Get all of the room's images.
     */
    public function images(): MorphMany
        {
            return $this->morphMany(Image::class, 'imageable');
        }

    /**
     * Get the room's featured main image.
     */
    public function mainImage(): MorphOne
        {
            return $this->morphOne(Image::class, 'imageable')->where('type', 'main');
        }

    /**
     * Get the room's gallery images.
     */
    public function gallery(): MorphMany
        {
            return $this->morphMany(Image::class, 'imageable')->where('type', 'gallery');
        }

    /**
     * Relationship with Amenities
     */
    public function amenities()
        {
            return $this->belongsToMany(Amenity::class, 'amenity_room');
        }


        
        protected static function booted()
        {
            static::deleting(function ($room) {
                // 1. Get all images associated with this room
                foreach ($room->images as $image) {
                    // 2. Delete the physical file from the storage folder
                    Storage::disk('public')->delete($image->url);
                    
                    // 3. Delete the row from the images table
                    $image->delete();
                }
            });
        }
        
}
