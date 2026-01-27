<?php

namespace App\Models;

use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Room extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = ['name','capacity','type','price','status'];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }


    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }


}
