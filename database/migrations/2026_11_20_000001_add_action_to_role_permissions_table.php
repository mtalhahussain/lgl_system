<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Role permissions gain an action (view/create/edit/delete). Existing Teacher/Student rows become "view".
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->string('action', 20)->default('view')->after('module');
        });
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->unique(['role', 'module', 'action']);
        });
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropUnique(['role', 'module']);
        });
    }

    public function down(): void
    {
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropUnique(['role', 'module', 'action']);
        });
        \Illuminate\Support\Facades\DB::table('role_permissions')->where('action', '!=', 'view')->delete();
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->unique(['role', 'module']);
            $table->dropColumn('action');
        });
    }
};
