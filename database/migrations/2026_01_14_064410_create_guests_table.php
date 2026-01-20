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
        Schema::create('guests', function (Blueprint $table) {
            $table->id();

            // Basic Info
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('contact_num');
            $table->enum('gender', ['male', 'female', 'other'])->nullable();

            // Identification
            $table->string('id_type');   // Passport, PhilID, etc.
            $table->string('id_number');

            // Guest Type
            $table->boolean('is_international')->default(false);
            $table->string('country')->default('Philippines');

            // Local (Philippines) Address
            $table->string('province')->nullable();
            $table->string('municipality')->nullable();
            $table->string('barangay')->nullable();

            // International Address
            $table->string('city')->nullable();
            $table->string('state_region')->nullable();

            // Optional: ID upload in future
            // $table->string('id_image_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
