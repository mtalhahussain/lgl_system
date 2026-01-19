<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->date('session_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('topic');
            $table->text('description')->nullable();
            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled'])->default('scheduled');
            $table->text('meeting_link')->nullable();
            $table->text('recording_link')->nullable();
            $table->text('materials')->nullable(); // JSON array of file paths
            $table->text('homework')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'session_date']);
            $table->index(['session_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};