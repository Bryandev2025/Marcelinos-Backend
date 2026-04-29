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
            $table->enum('booking_status', [
                'reserved',
                'occupied',
                'completed',
                'cancelled',
                'rescheduled',
            ])->nullable()->after('total_price');

            $table->enum('payment_status', [
                'unpaid',
                'partial',
                'paid',
            ])->nullable()->after('booking_status');
        });

        // Stay progress from legacy single column
        DB::statement("UPDATE bookings SET booking_status = CASE `status`
            WHEN 'occupied' THEN 'occupied'
            WHEN 'completed' THEN 'completed'
            WHEN 'cancelled' THEN 'cancelled'
            WHEN 'rescheduled' THEN 'rescheduled'
            ELSE 'reserved'
        END");

        // Payment state from ledger (preferred over legacy status)
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite doesn't support MySQL-style UPDATE ... LEFT JOIN, so use correlated subqueries.
            $paidSumExpr = "(SELECT COALESCE(SUM(partial_amount), 0) FROM payments WHERE booking_id = bookings.id)";

            DB::statement("UPDATE bookings SET payment_status = CASE
                WHEN {$paidSumExpr} <= 0.0001 THEN 'unpaid'
                WHEN {$paidSumExpr} < (bookings.total_price - 0.009) THEN 'partial'
                ELSE 'paid'
            END");
        } else {
            DB::statement('UPDATE bookings b
                LEFT JOIN (
                    SELECT booking_id, COALESCE(SUM(partial_amount), 0) AS paid_sum
                    FROM payments
                    GROUP BY booking_id
                ) p ON p.booking_id = b.id
                SET b.payment_status = CASE
                    WHEN COALESCE(p.paid_sum, 0) <= 0.0001 THEN \'unpaid\'
                    WHEN COALESCE(p.paid_sum, 0) < (CAST(b.total_price AS DECIMAL(12, 2)) - 0.009) THEN \'partial\'
                    ELSE \'paid\'
                END');
        }

        // Some DB schemas / test environments may already have `status` removed.
        // SQLite also tends to be stricter about dropping columns.
        if (DB::connection()->getDriverName() === 'sqlite') {
            try {
                if (Schema::hasColumn('bookings', 'status')) {
                    Schema::table('bookings', function (Blueprint $table) {
                        $table->dropColumn('status');
                    });
                }
            } catch (\Throwable $e) {
                // Best-effort: if SQLite can't drop the legacy column, the app can still run.
            }
        } else {
            if (Schema::hasColumn('bookings', 'status')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->dropColumn('status');
                });
            }
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            // MySQL-only: enforce ENUM + defaults. SQLite treats enums as generic strings.
            DB::statement("ALTER TABLE bookings MODIFY booking_status ENUM(
                'reserved','occupied','completed','cancelled','rescheduled'
            ) NOT NULL DEFAULT 'reserved'");

            DB::statement("ALTER TABLE bookings MODIFY payment_status ENUM(
                'unpaid','partial','paid'
            ) NOT NULL DEFAULT 'unpaid'");
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', [
                'unpaid',
                'partial',
                'occupied',
                'completed',
                'checked_in',
                'checked_out',
                'paid',
                'cancelled',
                'rescheduled',
            ])->default('unpaid')->after('total_price');
        });

        DB::statement("UPDATE bookings SET `status` = CASE booking_status
            WHEN 'cancelled' THEN 'cancelled'
            WHEN 'rescheduled' THEN 'rescheduled'
            WHEN 'completed' THEN 'completed'
            WHEN 'occupied' THEN 'occupied'
            WHEN 'reserved' THEN CASE payment_status
                WHEN 'partial' THEN 'partial'
                WHEN 'paid' THEN 'paid'
                ELSE 'unpaid'
            END
            ELSE 'unpaid'
        END");

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['booking_status', 'payment_status']);
        });
    }
};
