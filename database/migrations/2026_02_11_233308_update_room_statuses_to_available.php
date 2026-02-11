<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update any rooms with invalid statuses to 'available'
        DB::table('rooms')
            ->whereIn('status', ['occupied', 'cleaning'])
            ->update(['status' => 'available']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed as this is a data fix
    }
};
