<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('guest_name_snapshot')->nullable()->after('guest_id');
            $table->string('guest_email_snapshot')->nullable()->after('guest_name_snapshot');
            $table->string('guest_contact_snapshot')->nullable()->after('guest_email_snapshot');
            $table->text('guest_address_snapshot')->nullable()->after('guest_contact_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'guest_name_snapshot',
                'guest_email_snapshot',
                'guest_contact_snapshot',
                'guest_address_snapshot',
            ]);
        });
    }
};

