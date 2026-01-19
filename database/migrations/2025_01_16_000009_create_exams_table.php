<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['midterm', 'final', 'quiz', 'speaking', 'listening', 'writing', 'reading']);
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('max_marks', 5, 2);
            $table->decimal('passing_marks', 5, 2);
            $table->text('description')->nullable();
            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();

            $table->index(['batch_id', 'exam_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};