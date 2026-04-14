<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'provider')) {
                $table->string('provider', 30)->nullable()->after('is_fullypaid');
            }
            if (! Schema::hasColumn('payments', 'provider_ref')) {
                $table->string('provider_ref')->nullable()->after('provider');
            }
            if (! Schema::hasColumn('payments', 'provider_status')) {
                $table->string('provider_status', 30)->nullable()->after('provider_ref');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'provider_status')) {
                $table->dropColumn('provider_status');
            }
            if (Schema::hasColumn('payments', 'provider_ref')) {
                $table->dropColumn('provider_ref');
            }
            if (Schema::hasColumn('payments', 'provider')) {
                $table->dropColumn('provider');
            }
        });
    }
};
