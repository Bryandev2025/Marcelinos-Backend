<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->decimal('seminar_price', 10, 2)->default(3000)->after('price');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('venue_event_type', 32)->nullable()->after('no_of_days');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('venue_event_type');
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn('seminar_price');
        });
    }
};
