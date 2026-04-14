<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookings', 'payment_method')) {
                $table->string('payment_method', 20)->nullable()->after('status');
            }
            if (! Schema::hasColumn('bookings', 'online_payment_plan')) {
                $table->string('online_payment_plan', 30)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('bookings', 'xendit_invoice_id')) {
                $table->string('xendit_invoice_id')->nullable()->after('online_payment_plan');
            }
            if (! Schema::hasColumn('bookings', 'xendit_invoice_url')) {
                $table->text('xendit_invoice_url')->nullable()->after('xendit_invoice_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            if (Schema::hasColumn('bookings', 'xendit_invoice_url')) {
                $table->dropColumn('xendit_invoice_url');
            }
            if (Schema::hasColumn('bookings', 'xendit_invoice_id')) {
                $table->dropColumn('xendit_invoice_id');
            }
            if (Schema::hasColumn('bookings', 'online_payment_plan')) {
                $table->dropColumn('online_payment_plan');
            }
            if (Schema::hasColumn('bookings', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
