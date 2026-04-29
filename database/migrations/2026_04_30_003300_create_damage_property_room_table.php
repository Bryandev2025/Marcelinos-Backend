<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_property_room', function (Blueprint $table) {
            $table->foreignId('damage_property_id')->constrained('damage_properties')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['damage_property_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_property_room');
    }
};

