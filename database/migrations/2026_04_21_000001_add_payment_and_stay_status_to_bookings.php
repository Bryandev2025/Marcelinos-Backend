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
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])
                ->default('unpaid')
                ->after('status');
            $table->enum('stay_status', ['reserved', 'occupied', 'completed', 'cancelled', 'rescheduled'])
                ->default('reserved')
                ->after('payment_status');

            $table->index('payment_status');
            $table->index('stay_status');
        });

        // -----------------------------
        // Backfill from legacy `status`
        // -----------------------------

        // Stay status is lifecycle-based (reserved/occupied/completed/cancelled/rescheduled).
        DB::table('bookings')
            ->whereIn('status', ['occupied', 'checked_in'])
            ->update(['stay_status' => 'occupied']);

        DB::table('bookings')
            ->whereIn('status', ['completed', 'checked_out'])
            ->update(['stay_status' => 'completed']);

        DB::table('bookings')
            ->where('status', 'cancelled')
            ->update(['stay_status' => 'cancelled']);

        DB::table('bookings')
            ->where('status', 'rescheduled')
            ->update(['stay_status' => 'rescheduled']);

        // Payment status is financial-based (unpaid/partial/paid).
        DB::table('bookings')
            ->where('status', 'paid')
            ->update(['payment_status' => 'paid']);

        DB::table('bookings')
            ->where('status', 'partial')
            ->update(['payment_status' => 'partial']);

        DB::table('bookings')
            ->where('status', 'unpaid')
            ->update(['payment_status' => 'unpaid']);

        // For any other legacy statuses, compute payment_status from payments total.
        // Note: payments.partial_amount is stored as integer; bookings.total_price is decimal.
        DB::statement(<<<'SQL'
UPDATE bookings b
LEFT JOIN (
  SELECT booking_id, COALESCE(SUM(partial_amount), 0) AS total_paid
  FROM payments
  GROUP BY booking_id
) p ON p.booking_id = b.id
SET b.payment_status =
  CASE
    WHEN COALESCE(p.total_paid, 0) >= COALESCE(b.total_price, 0) AND COALESCE(b.total_price, 0) > 0 THEN 'paid'
    WHEN COALESCE(p.total_paid, 0) > 0 THEN 'partial'
    ELSE 'unpaid'
  END
WHERE (b.payment_status IS NULL OR b.payment_status NOT IN ('unpaid','partial','paid'));
SQL);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['stay_status']);
            $table->dropColumn(['payment_status', 'stay_status']);
        });
    }
};

