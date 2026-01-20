<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $fillable = [
    'first_name',
    'middle_name',
    'last_name',
    'email',
    'contact_num',
    'gender',
    'id_type',
    'id_number',
    'is_international',
    'country',
    'province',
    'municipality',
    'barangay',
    'city',
    'state_region',
    ];



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
