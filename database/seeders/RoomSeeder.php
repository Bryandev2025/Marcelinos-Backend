<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\BedSpecification;
use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Seed sample room inventory and relation data.
     */
    public function run(): void
    {
        $bedSpecMap = BedSpecification::query()
            ->pluck('id', 'specification');

        $amenityMap = Amenity::query()
            ->pluck('id', 'name');

        $standardDescription = 'Cozy standard room designed for comfort and convenience. Features two single beds, ideal for up to 2 guests. Perfect for short stays or business trips, offering a simple and relaxing space with essential amenities, including free Wi-Fi, for a pleasant and hassle-free experience.';
        $deluxeDescription = 'Spacious deluxe room ideal for up to 3 guests. Features one double bed and one single bed, perfect for small groups or families. Designed for comfort and relaxation, with a cozy ambiance and essential amenities, including free Wi-Fi, for a pleasant and enjoyable stay.';
        $familyDescription = 'Comfortable family room perfect for up to 4 guests. Features one queen-size bed and one single bed, offering a spacious and relaxing environment for families or groups. Designed for convenience and comfort, with essential amenities including free Wi-Fi for a pleasant and enjoyable stay.';

        $rooms = [
            [
                'name' => 'Room 101',
                'description' => $standardDescription,
                'capacity' => 2,
                'type' => Room::TYPE_STANDARD,
                'price' => 1500,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['2 Single Beds'],
                'amenities' => ['Free WiFi'],
            ],
            [
                'name' => 'Room 102',
                'description' => $standardDescription,
                'capacity' => 2,
                'type' => Room::TYPE_STANDARD,
                'price' => 1500,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['2 Single Beds'],
                'amenities' => ['Free WiFi'],
            ],
            [
                'name' => 'Room 103',
                'description' => $standardDescription,
                'capacity' => 2,
                'type' => Room::TYPE_STANDARD,
                'price' => 1500,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['2 Single Beds'],
                'amenities' => ['Free WiFi'],
            ],
            [
                'name' => 'Room 104',
                'description' => $standardDescription,
                'capacity' => 2,
                'type' => Room::TYPE_STANDARD,
                'price' => 1500,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['2 Single Beds'],
                'amenities' => ['Free WiFi'],
            ],
            [
                'name' => 'Room 105',
                'description' => $standardDescription,
                'capacity' => 2,
                'type' => Room::TYPE_STANDARD,
                'price' => 1500,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['2 Single Beds'],
                'amenities' => ['Free WiFi'],
            ],
            [
                'name' => 'Room 106',
                'description' => $standardDescription,
                'capacity' => 2,
                'type' => Room::TYPE_STANDARD,
                'price' => 1500,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['2 Single Beds'],
                'amenities' => ['Free WiFi'],
            ],
            [
                'name' => 'Room 107',
                'description' => $deluxeDescription,
                'capacity' => 3,
                'type' => Room::TYPE_DELUXE,
                'price' => 2200,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['1 Double Bed', '1 Single Bed'],
                'amenities' => ['Free WiFi'],
            ],
            [
                'name' => 'Room 108',
                'description' => $deluxeDescription,
                'capacity' => 3,
                'type' => Room::TYPE_DELUXE,
                'price' => 2200,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['1 Double Bed', '1 Single Bed'],
                'amenities' => ['Free WiFi'],
            ],
            [
                'name' => 'Room 201',
                'description' => $deluxeDescription,
                'capacity' => 3,
                'type' => Room::TYPE_DELUXE,
                'price' => 2200,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['1 Double Bed', '1 Single Bed'],
                'amenities' => ['Free WiFi'],
            ],
            [
                'name' => 'Room 202',
                'description' => $deluxeDescription,
                'capacity' => 3,
                'type' => Room::TYPE_DELUXE,
                'price' => 2200,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['1 Double Bed', '1 Single Bed'],
                'amenities' => ['Free WiFi'],
            ],
            [
                'name' => 'Room 205',
                'description' => $deluxeDescription,
                'capacity' => 3,
                'type' => Room::TYPE_DELUXE,
                'price' => 2200,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['1 Double Bed', '1 Single Bed'],
                'amenities' => ['Free WiFi'],
            ],
            [
                'name' => 'Room 206',
                'description' => $deluxeDescription,
                'capacity' => 3,
                'type' => Room::TYPE_DELUXE,
                'price' => 2200,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['1 Double Bed', '1 Single Bed'],
                'amenities' => ['Free WiFi'],
            ],
            [
                'name' => 'Room 203',
                'description' => $familyDescription,
                'capacity' => 4,
                'type' => Room::TYPE_FAMILY,
                'price' => 3000,
                'status' => Room::STATUS_AVAILABLE,
                'bed_specs' => ['1 Queen Bed and 1 Single Bed'],
                'amenities' => ['Free WiFi'],
            ],
        ];

        foreach ($rooms as $roomData) {
            $bedSpecNames = $roomData['bed_specs'];
            $amenityNames = $roomData['amenities'];

            unset($roomData['bed_specs'], $roomData['amenities']);

            /** @var Room $room */
            $room = Room::query()->updateOrCreate(
                ['name' => $roomData['name']],
                $roomData
            );

            $bedSpecIds = collect($bedSpecNames)
                ->map(fn (string $name) => $bedSpecMap[$name] ?? null)
                ->filter()
                ->values()
                ->all();

            $amenityIds = collect($amenityNames)
                ->map(fn (string $name) => $amenityMap[$name] ?? null)
                ->filter()
                ->values()
                ->all();

            $room->bedSpecifications()->sync($bedSpecIds);
            $room->amenities()->sync($amenityIds);
        }
    }
}
