<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('special_discount_type')->nullable()->after('total_price'); // percent|fixed
            $table->decimal('special_discount_value', 10, 2)->nullable()->after('special_discount_type');
            $table->string('special_discount_reason_code')->nullable()->after('special_discount_value');
            $table->text('special_discount_note')->nullable()->after('special_discount_reason_code');

            // Computed + audit snapshot so reporting stays stable.
            $table->decimal('special_discount_original_total_price', 10, 2)->nullable()->after('special_discount_note');
            $table->decimal('special_discount_amount_applied', 10, 2)->nullable()->after('special_discount_original_total_price');

            $table->foreignId('special_discounted_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('special_discount_amount_applied');
            $table->timestamp('special_discounted_at')->nullable()->after('special_discounted_by_user_id');
            $table->foreignId('special_discount_last_modified_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('special_discounted_at');
            $table->timestamp('special_discount_last_modified_at')->nullable()->after('special_discount_last_modified_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('special_discounted_by_user_id');
            $table->dropConstrainedForeignId('special_discount_last_modified_by_user_id');

            $table->dropColumn([
                'special_discount_type',
                'special_discount_value',
                'special_discount_reason_code',
                'special_discount_note',
                'special_discount_original_total_price',
                'special_discount_amount_applied',
                'special_discounted_at',
                'special_discount_last_modified_at',
            ]);
        });
    }
};

