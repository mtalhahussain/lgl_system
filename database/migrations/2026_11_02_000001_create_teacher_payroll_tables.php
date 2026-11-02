<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 20);
            $table->char('period', 7)->nullable();
            $table->foreignId('batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('student_count')->nullable();
            $table->decimal('rate', 12, 2);
            $table->decimal('amount', 12, 2);
            // salary:{teacher}:{period} or batch:{batch}; cleared on void so a corrected due can be generated.
            $table->string('active_key', 80)->nullable()->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->index(['teacher_id', 'kind']);
        });
        Schema::create('teacher_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_due_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->index();
            $table->foreignId('paid_by')->constrained('users')->restrictOnDelete();
            $table->string('voucher_number', 40)->unique();
            $table->string('submission_key', 64)->unique();
            $table->string('payload_hash', 64);
            $table->json('slip_details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('teacher_payout_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_payout_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('reversed_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Payroll history must be preserved. Use a reviewed forward change.');
    }
};
