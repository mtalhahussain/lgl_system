<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Do not silently promote, delete or relabel legacy accounts.
        if (DB::table('users')->whereNotIn('role', ['admin', 'teacher', 'student'])->exists()) {
            throw new RuntimeException('Unsupported user roles remain. Review legacy accounts before applying the three-role schema.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'teacher', 'student'])->change();
        });
    }

    public function down(): void
    {
        // Restore historical schema compatibility without creating an account.
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'teacher', 'student', 'accountant'])->change();
        });
    }
};
