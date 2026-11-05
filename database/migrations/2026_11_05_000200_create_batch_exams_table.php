<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->restrictOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('final');
            $table->date('exam_date');
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
            $table->index(['batch_id', 'exam_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_exams');
    }
};
