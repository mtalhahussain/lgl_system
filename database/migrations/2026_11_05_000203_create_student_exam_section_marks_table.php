<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL may retain the table when adding the unique index fails.
        if (! Schema::hasTable('student_exam_section_marks')) {
            Schema::create('student_exam_section_marks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_exam_result_id')->constrained('student_exam_results')->cascadeOnDelete();
                $table->foreignId('exam_section_id')->constrained('exam_sections')->restrictOnDelete();
                $table->decimal('marks_obtained', 6, 2);
                $table->boolean('passed')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('student_exam_section_marks', ['student_exam_result_id', 'exam_section_id'], 'unique')) {
            Schema::table('student_exam_section_marks', function (Blueprint $table) {
                $table->unique(['student_exam_result_id', 'exam_section_id'], 'exam_result_section_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_exam_section_marks');
    }
};
