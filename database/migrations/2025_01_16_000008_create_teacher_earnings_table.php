<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->year('year');
            $table->tinyInteger('month'); // 1-12
            $table->integer('students_count');
            $table->decimal('per_student_amount', 10, 2);
            $table->decimal('total_earning', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->date('paid_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'partially_paid'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'batch_id', 'year', 'month']);
            $table->index(['teacher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_earnings');
    }
};