<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('level', ['A1', 'A2', 'B1', 'B2', 'C1', 'C2']);
            $table->text('description')->nullable();
            $table->decimal('total_fee', 10, 2);
            $table->decimal('teacher_per_student_amount', 10, 2);
            $table->integer('duration_weeks');
            $table->integer('sessions_per_week')->default(2);
            $table->integer('session_duration_minutes')->default(90);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['name', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};