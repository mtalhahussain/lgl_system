<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->string('certificate_number')->unique();
            $table->date('issued_date');
            $table->decimal('final_grade', 5, 2)->nullable();
            $table->char('grade_letter', 2)->nullable();
            $table->string('certificate_url')->nullable(); // Path to PDF
            $table->boolean('is_printed')->default(false);
            $table->date('printed_date')->nullable();
            $table->foreignId('issued_by')->constrained('users'); // Admin who issued
            $table->timestamps();

            $table->index(['student_id', 'course_id']);
            $table->index('certificate_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};