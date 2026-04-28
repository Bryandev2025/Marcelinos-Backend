<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_checklist_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('room_checklist_id')->constrained('room_checklists')->cascadeOnDelete();

            $table->string('label');
            $table->string('status')->default('good'); // good | broken | missing
            $table->text('notes')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['room_checklist_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_checklist_items');
    }
};

