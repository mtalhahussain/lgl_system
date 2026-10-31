<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('transferred_from_enrollment_id')->nullable()->unique()
                ->constrained('enrollments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Transfer history must be preserved. Use a reviewed forward change.');
    }
};
