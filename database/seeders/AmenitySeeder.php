<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /**
     * Seed amenities commonly used by rooms and venues.
     */
    public function run(): void
    {
        $amenities = [
            'Air Conditioning',
            'Free WiFi',
        ];

        foreach ($amenities as $name) {
            Amenity::query()->firstOrCreate(['name' => $name]);
        }
    }
}
