<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE bookings MODIFY payment_status ENUM(
                'unpaid','partial','paid','refund_pending','non_refundable','refunded'
            ) NOT NULL DEFAULT 'unpaid'");
        }
    }

    public function down(): void
    {
        DB::statement("UPDATE bookings
            SET payment_status = 'refund_pending'
            WHERE payment_status = 'non_refundable'");

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE bookings MODIFY payment_status ENUM(
                'unpaid','partial','paid','refund_pending','refunded'
            ) NOT NULL DEFAULT 'unpaid'");
        }
    }
};
