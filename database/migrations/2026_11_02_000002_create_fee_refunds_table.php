<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reconciled cache of refunds; the schedule keeps covering paid amounts (scheduled = net + refunded).
        Schema::table('enrollments', function (Blueprint $table) {
            $table->decimal('refunded_amount', 12, 2)->default(0)->after('paid_amount');
        });
        Schema::create('fee_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('reference')->nullable();
            $table->text('reason');
            $table->timestamp('refunded_at')->index();
            $table->foreignId('refunded_by')->constrained('users')->restrictOnDelete();
            $table->string('voucher_number', 40)->unique();
            $table->string('submission_key', 64)->unique();
            $table->string('payload_hash', 64);
            $table->json('slip_details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Refund history must be preserved. Use a reviewed forward change.');
    }
};
