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
        Schema::table('users', function (Blueprint $table) {
            // Add only missing teacher information fields
            $table->string('qualification')->nullable()->after('bio');
            $table->integer('experience_years')->default(0)->after('qualification');
            $table->string('specialization')->nullable()->after('experience_years');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['qualification', 'experience_years', 'specialization']);
        });
    }
};
