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

    protected $fillable = ['name','capacity','type','price','status'];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('room_images');
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function ImagesRoom()
    {
       return $this->morphMany(Image::class, 'imageable')->where('type', 'featured');
    }


}
