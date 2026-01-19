<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->date('enrollment_date');
            $table->enum('status', ['active', 'transferred', 'dropped', 'completed'])->default('active');
            $table->decimal('total_fee', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('transferred_to_batch_id')->nullable()->constrained('batches');
            $table->date('transfer_date')->nullable();
            $table->date('dropout_date')->nullable();
            $table->text('dropout_reason')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};