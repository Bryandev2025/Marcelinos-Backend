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

    public static function store($request)
    {
        $validated = $request->validate([
            'first_name'       => 'required|string|max:100',
            'middle_name'      => 'nullable|string|max:100',
            'last_name'        => 'required|string|max:100',
            'email'            => 'required|email|unique:guests,email',
            'contact_num'      => 'required|string|max:20',
            'gender'           => 'nullable|in:Male,Female,Other',
            'id_type'          => 'required|string|max:50',
            'id_number'        => 'required|string|max:100',
            'is_international' => 'required|boolean',
            'country'          => 'nullable|string|max:100',
            'province'         => 'nullable|string|max:100',
            'municipality'     => 'nullable|string|max:100',
            'barangay'         => 'nullable|string|max:100',
            'city'             => 'nullable|string|max:100',
            'state_region'     => 'nullable|string|max:100',
        ]);

        // Default country logic
        if (!$validated['is_international']) {
            $validated['country'] = 'Philippines';
            $validated['city'] = null;
            $validated['state_region'] = null;
        } else {
            $validated['province'] = null;
            $validated['municipality'] = null;
            $validated['barangay'] = null;
        }

        return self::create($validated);
    }
}