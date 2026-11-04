<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A batch's recurring weekly class days and times (a template).
     * Editing it does not rewrite already-generated class_sessions; it only
     * drives "Generate from schedule" and the planned weekly-hours display.
     */
    public function up(): void
    {
        Schema::create('batch_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->enum('weekday', ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']);
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->index(['batch_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_schedules');
    }
};
