<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('refund_alert_sent_at')->nullable()->after('testimonial_feedback_sent_at');
            $table->timestamp('refund_guest_notice_sent_at')->nullable()->after('refund_alert_sent_at');
            $table->timestamp('refund_guest_confirmation_sent_at')->nullable()->after('refund_guest_notice_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'refund_alert_sent_at',
                'refund_guest_notice_sent_at',
                'refund_guest_confirmation_sent_at',
            ]);
        });
    }
};
