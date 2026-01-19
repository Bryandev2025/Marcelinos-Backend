<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $guarded = []; // Allows all fields to be filled by Filament


        // Link to the ID photo in the images table
        public function identification()
        {
            return $this->morphOne(Image::class, 'imageable')->where('type', 'identification');
        }

        public function bookings()
        {
            return $this->hasMany(Booking::class);
        }
}
