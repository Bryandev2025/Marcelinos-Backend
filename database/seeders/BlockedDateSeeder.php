<?php

namespace Database\Seeders;

use App\Models\BlockedDate;
use Illuminate\Database\Seeder;

class BlockedDateSeeder extends Seeder
{
    /**
     * Seed default blocked calendar dates (global).
     */
    public function run(): void
    {
        BlockedDate::updateOrCreate(
            ['date' => '2026-03-29'],
            ['reason' => 'Maintainance'],
        );
    }
}
