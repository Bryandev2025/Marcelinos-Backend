<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Refactor: one booking row can have many rooms and many venues via pivot tables.
     */
    public function up(): void
    {
        // Pivot: booking <-> rooms (many-to-many)
        Schema::create('booking_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['booking_id', 'room_id']);
        });

        // Pivot: booking <-> venues (many-to-many)
        Schema::create('booking_venue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['booking_id', 'venue_id']);
        });

        // Migrate existing booking -> room data into pivot
        $bookings = DB::table('bookings')->whereNotNull('room_id')->get(['id', 'room_id']);
        foreach ($bookings as $row) {
            DB::table('booking_room')->insert([
                'booking_id' => $row->id,
                'room_id'    => $row->room_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Drop foreign and column from bookings
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropColumn('room_id');
        });

        // If venue_id was added in another migration, drop it here too
        if (Schema::hasColumn('bookings', 'venue_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropForeign(['venue_id']);
                $table->dropColumn('venue_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add room_id to bookings
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('guest_id')->constrained()->cascadeOnDelete();
        });

        // Restore one room per booking from pivot (pick first room per booking)
        $pivots = DB::table('booking_room')->orderBy('booking_id')->get();
        $seen = [];
        foreach ($pivots as $row) {
            if (!isset($seen[$row->booking_id])) {
                $seen[$row->booking_id] = true;
                DB::table('bookings')->where('id', $row->booking_id)->update(['room_id' => $row->room_id]);
            }
        }

        Schema::dropIfExists('booking_venue');
        Schema::dropIfExists('booking_room');
    }
};
