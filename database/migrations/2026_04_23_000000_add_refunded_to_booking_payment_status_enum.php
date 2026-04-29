<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE bookings MODIFY payment_status ENUM(
                'unpaid','partial','paid','refunded'
            ) NOT NULL DEFAULT 'unpaid'");
        }
    }

    public function down(): void
    {
        DB::statement("UPDATE bookings
            SET payment_status = 'paid'
            WHERE payment_status = 'refunded'");

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE bookings MODIFY payment_status ENUM(
                'unpaid','partial','paid'
            ) NOT NULL DEFAULT 'unpaid'");
        }
    }
};
