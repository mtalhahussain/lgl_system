<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('receipt_number', 40)->unique();
            $table->string('submission_key', 64)->unique();
            $table->string('payload_hash', 64);
            $table->string('legacy_source')->nullable()->unique();
            $table->json('receipt_details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_installment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->unique(['payment_id', 'fee_installment_id']);
        });
        Schema::create('payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('reversed_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->text('reason')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Ledger history cannot be dropped by rollback. Use a reviewed forward migration or restore plan.');
    }
};
