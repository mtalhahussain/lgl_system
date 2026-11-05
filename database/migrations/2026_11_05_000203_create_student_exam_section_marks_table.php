<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_exam_section_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_exam_result_id')->constrained('student_exam_results')->cascadeOnDelete();
            $table->foreignId('exam_section_id')->constrained('exam_sections')->restrictOnDelete();
            $table->decimal('marks_obtained', 6, 2);
            $table->boolean('passed')->default(false);
            $table->timestamps();
            $table->unique(['student_exam_result_id', 'exam_section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_exam_section_marks');
    }
};
