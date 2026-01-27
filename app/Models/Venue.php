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
        return $this->hasMany(Booking::class);
    }

      public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }

}