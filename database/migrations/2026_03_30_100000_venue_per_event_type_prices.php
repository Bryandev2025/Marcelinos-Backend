<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->decimal('wedding_price', 10, 2)->default(8000)->after('capacity');
            $table->decimal('birthday_price', 10, 2)->default(8000)->after('wedding_price');
            $table->decimal('meeting_staff_price', 10, 2)->default(8000)->after('birthday_price');
        });

        if (Schema::hasColumn('venues', 'price')) {
            DB::statement('UPDATE venues SET wedding_price = price, birthday_price = price, meeting_staff_price = seminar_price');
        }

        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['price', 'seminar_price']);
        });

        DB::table('bookings')
            ->where('venue_event_type', 'seminar')
            ->update(['venue_event_type' => 'meeting_staff']);
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('seminar_price', 10, 2)->default(3000);
        });

        if (Schema::hasColumn('venues', 'wedding_price')) {
            DB::statement('UPDATE venues SET price = wedding_price, seminar_price = meeting_staff_price');
        }

        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['wedding_price', 'birthday_price', 'meeting_staff_price']);
        });

        DB::table('bookings')
            ->where('venue_event_type', 'meeting_staff')
            ->update(['venue_event_type' => 'seminar']);
    }
};
