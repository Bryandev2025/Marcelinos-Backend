<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // SHA-256 hex (64 chars). Optional, but required for /api/billing/{id}?token=... access.
            $table->string('access_token', 64)->nullable()->index();
            // Optional expiry timestamp for the raw token embedded in guest links.
            $table->timestamp('token_expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['access_token', 'token_expires_at']);
        });
    }
};

