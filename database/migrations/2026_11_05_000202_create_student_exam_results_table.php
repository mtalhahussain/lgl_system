<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_exam_id')->constrained('batch_exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->boolean('overall_passed')->default(false);
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at');
            $table->timestamps();
            $table->unique(['batch_exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_exam_results');
    }
};
