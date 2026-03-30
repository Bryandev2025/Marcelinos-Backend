<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM(
            'unpaid',
            'partial',
            'occupied',
            'completed',
            'checked_in',
            'checked_out',
            'paid',
            'cancelled',
            'rescheduled'
        ) NOT NULL DEFAULT 'unpaid'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM(
            'unpaid',
            'occupied',
            'completed',
            'checked_in',
            'checked_out',
            'paid',
            'cancelled',
            'rescheduled'
        ) NOT NULL DEFAULT 'unpaid'");
    }
};

