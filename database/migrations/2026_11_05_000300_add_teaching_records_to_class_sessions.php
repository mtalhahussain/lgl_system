<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->string('teaching_status', 16)->nullable()->index();
            $table->timestamp('teaching_started_at')->nullable();
            $table->timestamp('teaching_recorded_at')->nullable();
            $table->foreignId('teaching_recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('teaching_reason')->nullable();
            $table->foreignId('makeup_for_id')->nullable()->constrained('class_sessions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Teaching and makeup history must be retained. Use a reviewed forward migration.');
    }
};
