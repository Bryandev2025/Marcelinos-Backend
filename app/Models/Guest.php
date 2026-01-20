<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Guest extends Model
{

    use HasFactory;

      // Fillable fields for mass assignment
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
        // 'id_image_path', // uncomment if you add ID upload later
    ];

    // Cast fields
    protected $casts = [
        'is_international' => 'boolean',
    ];

     /**
     * Get the full name of the guest
     */
    public function getFullNameAttribute(): string
    {
        $middle = $this->middle_name ? " {$this->middle_name} " : " ";
        return "{$this->first_name}{$middle}{$this->last_name}";
    }

    /**
     * Relationships
     */


    /**
     * Scope for international guests
     */
    public function scopeInternational($query)
    {
        return $query->where('is_international', true);
    }

    /**
     * Scope for local guests
     */
    public function scopeLocal($query)
    {
        return $query->where('is_international', false);
    }


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
