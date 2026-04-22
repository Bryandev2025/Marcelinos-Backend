<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('booking_status');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY booking_status ENUM(
                'pending_verification',
                'reserved',
                'occupied',
                'completed',
                'cancelled',
                'rescheduled'
            ) NOT NULL DEFAULT 'reserved'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('bookings')
                ->where('booking_status', 'pending_verification')
                ->update(['booking_status' => 'reserved']);

            DB::statement("ALTER TABLE bookings MODIFY booking_status ENUM(
                'reserved',
                'occupied',
                'completed',
                'cancelled',
                'rescheduled'
            ) NOT NULL DEFAULT 'reserved'");
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
