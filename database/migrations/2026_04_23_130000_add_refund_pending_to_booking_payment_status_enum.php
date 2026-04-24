<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY payment_status ENUM(
            'unpaid','partial','paid','refund_pending','refunded'
        ) NOT NULL DEFAULT 'unpaid'");

        // Existing rescheduled + refunded rows came from amount recalculation only.
        // Move them to refund_pending so staff can explicitly complete the refund.
        DB::statement("UPDATE bookings
            SET payment_status = 'refund_pending'
            WHERE booking_status = 'rescheduled'
              AND payment_status = 'refunded'");
    }

    public function down(): void
    {
        DB::statement("UPDATE bookings
            SET payment_status = 'refunded'
            WHERE payment_status = 'refund_pending'");

        DB::statement("ALTER TABLE bookings MODIFY payment_status ENUM(
            'unpaid','partial','paid','refunded'
        ) NOT NULL DEFAULT 'unpaid'");
    }
};
