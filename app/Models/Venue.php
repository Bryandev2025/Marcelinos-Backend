<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\Storage;

class Venue extends Model
{
    protected $table = 'venues';
    protected $guarded = [];

    // GET ALL IMAGES FOR THE VENUE
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    // GET THE MAIN FEATURED IMAGE FOR THE VENUE
    public function mainImage(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')->where('type', 'main');
    }

    // GET GALLERY IMAGES FOR THE VENUE
    public function gallery(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')->where('type', 'gallery');
    }

    // RELATIONSHIP WITH AMENITIES
    public function amenities()
    {
        return $this->belongsToMany(Amenity::class, 'amenity_venue');
    }

    // HANDLE DELETION OF VENUE AND ITS IMAGES
    protected static function booted()
    {
        static::deleting(function ($venue) {
            foreach ($venue->images as $image) {
                // Delete the physical file from storage
                Storage::disk('public')->delete($image->url);

                // Delete the database record
                $image->delete();
            }
        });
    }
}
