<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')
            ->nullable()
            ->constrained()
            ->nullOnDelete();
            $table->foreignId('venue_id')
            ->nullable()
            ->constrained()
            ->nullOnDelete();
            $table->dateTime('check_in');
            $table->dateTime('check_out');
            $table->decimal('total_price', 10, 2);
            $table->enum('status', [
            'pending', 
            'confirmed', 
            'occupied', 
            'completed', 
            'cancelled', 
            'reschedule'
            ])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
