<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('reminder_sms_sent')->default(false)->after('reminder_sent_at');
            $table->timestamp('reminder_sms_sent_at')->nullable()->after('reminder_sms_sent');
            $table->text('reminder_sms_error')->nullable()->after('reminder_sms_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_sms_sent',
                'reminder_sms_sent_at',
                'reminder_sms_error',
            ]);
        });
    }
};
