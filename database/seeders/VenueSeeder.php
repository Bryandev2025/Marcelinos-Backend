<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class VenueSeeder extends Seeder
{
    /**
     * Seed venue inventory with per-event pricing.
     */
    public function run(): void
    {
        $amenityMap = Amenity::query()
            ->pluck('id', 'name');

        $venues = [
            [
                'name' => 'Marcelinos Grand Pavilion',
                'description' => 'Main covered venue ideal for weddings, birthdays, and corporate functions.',
                'capacity' => 300,
                'wedding_price' => 75000,
                'birthday_price' => 45000,
                'meeting_staff_price' => 30000,
                'status' => Venue::STATUS_AVAILABLE,
                'amenities' => ['Air Conditioning', 'Free WiFi', 'Parking Space', 'Sound System', 'Projector', 'Catering Area'],
            ],
            [
                'name' => 'AIR-CONDITIONED',
                'description' => 'Fully air-conditioned venue ideal for weddings, birthdays, and meetings. Comfortably accommodates up to 50 guests, offering a cozy and versatile space for intimate events, seminars, and special celebrations. Well-maintained, accessible, and perfect for creating memorable experiences in a comfortable setting.',
                'capacity' => 50,
                'wedding_price' => 8000,
                'birthday_price' => 8000,
                'meeting_staff_price' => 8000,
                'status' => Venue::STATUS_AVAILABLE,
                'amenities' => ['Air Conditioning'],
            ],
            [
                'name' => 'NON AIR-CONDITIONED',
                'description' => 'Spacious, naturally ventilated venue perfect for weddings, birthdays, and meetings. Accommodates up to 80 guests, offering a comfortable and budget-friendly setting for intimate gatherings, seminars, and special occasions. Ideal for those who prefer an open-air ambiance with a relaxed and refreshing atmosphere.',
                'capacity' => 80,
                'wedding_price' => 12000,
                'birthday_price' => 8000,
                'meeting_staff_price' => 6000,
                'status' => Venue::STATUS_AVAILABLE,
                'amenities' => [],
            ],
        ];

        foreach ($venues as $venueData) {
            $amenityNames = $venueData['amenities'];

            unset($venueData['amenities']);

            /** @var Venue $venue */
            $venue = Venue::query()->updateOrCreate(
                ['name' => $venueData['name']],
                $venueData
            );

            $venueAmenityIds = collect($amenityNames)
                ->map(fn (string $name) => $amenityMap[$name] ?? null)
                ->filter()
                ->values()
                ->all();

            $venue->amenities()->sync($venueAmenityIds);
        }
    }
}
