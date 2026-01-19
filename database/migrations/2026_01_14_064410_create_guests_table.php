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
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('contact_num');
            $table->string('gender')->nullable(); // male, female, other
            $table->string('id_type');   // Passport, PhilID
            $table->string('id_number'); 
            $table->boolean('is_international')->default(false);
            $table->string('country')->default('Philippines');
            // PH Fields
            $table->string('province')->nullable();
            $table->string('municipality')->nullable();
            $table->string('barangay')->nullable();
            // Int'l Fields
            $table->string('city')->nullable();
            $table->string('state_region')->nullable();
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
