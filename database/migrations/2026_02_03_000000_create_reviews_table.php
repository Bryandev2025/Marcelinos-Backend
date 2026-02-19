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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('guest_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('booking_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // This review is for the overall experience at Marcelinos Hotel
            // No more morphs or specific room/venue references

            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('comment')->nullable();

            $table->boolean('is_approved')->default(false);
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['guest_id', 'created_at']);
            $table->index(['is_approved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
