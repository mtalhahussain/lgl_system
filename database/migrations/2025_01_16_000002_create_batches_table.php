<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->string('name'); // e.g., "A1-2025-01", "B2-Evening-2025-02"
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('max_students');
            $table->enum('status', ['upcoming', 'ongoing', 'completed', 'cancelled'])->default('upcoming');
            $table->enum('meeting_platform', ['zoom', 'google_meet', 'in_person'])->default('in_person');
            $table->text('meeting_link')->nullable();
            $table->text('meeting_password')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};