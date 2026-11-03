<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('reference')->nullable();
            $table->string('receipt_number', 40)->unique();
            $table->string('submission_key', 64)->unique();
            $table->string('payload_hash', 64);
            $table->json('receipt_details');
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('enrollment_package_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Package receipts and enrollment history must be preserved. Use a reviewed forward change.');
    }
};
