<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('rooms')->insert([
            [
                'name' => 'Room 101',
                'capacity' => 2,
                'type' => 'standard',
                'price' => 1500.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Room 102',
                'capacity' => 2,
                'type' => 'standard',
                'price' => 1500.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Room 103',
                'capacity' => 2,
                'type' => 'standard',
                'price' => 1500.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Room 104',
                'capacity' => 2,
                'type' => 'standard',
                'price' => 1500.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Room 105',
                'capacity' => 2,
                'type' => 'standard',
                'price' => 1500.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Room 106',
                'capacity' => 2,
                'type' => 'standard',
                'price' => 1500.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Room 107',
                'capacity' => 3,
                'type' => 'deluxe',
                'price' => 2200.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Room 108',
                'capacity' => 3,
                'type' => 'deluxe',
                'price' => 2200.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Room 201',
                'capacity' => 3,
                'type' => 'deluxe',
                'price' => 2200.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Room 202',
                'capacity' => 3,
                'type' => 'deluxe',
                'price' => 2200.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Room 203',
                'capacity' => 3,
                'type' => 'family',
                'price' => 3000.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Room 204',
                'capacity' => 3,
                'type' => 'family',
                'price' => 3000.00,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
