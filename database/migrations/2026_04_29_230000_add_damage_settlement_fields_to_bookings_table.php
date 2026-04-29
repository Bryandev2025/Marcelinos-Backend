<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('has_damage_claim')
                ->default(false)
                ->after('payment_status');
            $table->string('damage_settlement_status')
                ->default('none')
                ->after('has_damage_claim');
            $table->text('damage_settlement_notes')
                ->nullable()
                ->after('damage_settlement_status');
            $table->foreignId('damage_settlement_marked_by')
                ->nullable()
                ->after('damage_settlement_notes')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('damage_settlement_marked_at')
                ->nullable()
                ->after('damage_settlement_marked_by');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('damage_settlement_marked_by');
            $table->dropColumn([
                'has_damage_claim',
                'damage_settlement_status',
                'damage_settlement_notes',
                'damage_settlement_marked_at',
            ]);
        });
    }
};
