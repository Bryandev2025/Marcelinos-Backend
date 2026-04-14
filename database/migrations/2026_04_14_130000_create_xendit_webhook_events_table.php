<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xendit_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key')->unique();
            $table->string('invoice_id')->nullable()->index();
            $table->string('external_id')->nullable()->index();
            $table->string('status')->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xendit_webhook_events');
    }
};
