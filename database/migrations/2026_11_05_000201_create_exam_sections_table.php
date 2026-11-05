<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_exam_id')->constrained('batch_exams')->cascadeOnDelete();
            $table->string('name', 40);
            $table->decimal('max_marks', 6, 2);
            $table->decimal('passing_marks', 6, 2);
            $table->unsignedTinyInteger('sort');
            $table->timestamps();
            $table->unique(['batch_exam_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sections');
    }
};
