<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Prevent DELETING the admin
        DB::unprepared("
            CREATE TRIGGER prevent_admin_delete
            BEFORE DELETE ON users
            FOR EACH ROW
            BEGIN
                IF OLD.role = 'admin' THEN
                    SIGNAL SQLSTATE '45000' 
                    SET MESSAGE_TEXT = 'CRITICAL: Admin user deletion is blocked at the database level.';
                END IF;
            END;
        ");

        // 2. Prevent CHANGING the role column from 'admin' to something else
        DB::unprepared("
            CREATE TRIGGER prevent_admin_role_change
            BEFORE UPDATE ON users
            FOR EACH ROW
            BEGIN
                IF OLD.role = 'admin' AND NEW.role <> 'admin' THEN
                    SIGNAL SQLSTATE '45000' 
                    SET MESSAGE_TEXT = 'CRITICAL: Admin role cannot be modified at the database level.';
                END IF;
            END;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS prevent_admin_delete");
        DB::unprepared("DROP TRIGGER IF EXISTS prevent_admin_role_change");
    }
};
