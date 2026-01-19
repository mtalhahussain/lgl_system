<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->decimal('marks_obtained', 5, 2);
            $table->char('grade', 2)->nullable(); // A+, A, B+, B, C+, C, F
            $table->boolean('is_passed');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
            $table->index(['student_id', 'is_passed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};