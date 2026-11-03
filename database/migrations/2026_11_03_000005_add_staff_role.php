<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff (receptionist, cleaner, security guard, …): paid and on the machine, but never signs in.
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'teacher', 'student', 'staff'])->change();
            $table->string('designation', 60)->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Staff accounts and their payroll history must be preserved. Use a reviewed forward change.');
    }
};
