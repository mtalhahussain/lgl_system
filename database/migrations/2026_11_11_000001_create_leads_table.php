<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // Intake source: public landing form, Meta lead ad, or manual entry.
            $table->string('source')->default('public')->index();
            // Lifecycle: new -> contacted -> qualified -> submitted -> converted | rejected.
            $table->string('status')->default('new')->index();

            // Captured lead fields (landing page / source payload).
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('cnic')->nullable()->index();
            $table->string('phone')->index();
            $table->string('email')->nullable();
            $table->string('program_interest')->nullable();
            $table->text('message')->nullable();

            // Original, unmodified source payload for audit/dispute.
            $table->json('raw_payload')->nullable();

            // Staff handling.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejected_reason', 500)->nullable();

            // Student-completed prefilled registration, awaiting Admin create.
            $table->json('submitted_payload')->nullable();
            $table->timestamp('submitted_at')->nullable();

            // Converted student account.
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        // Leads are applicant history; refuse destructive rollback (project convention).
        throw new RuntimeException('The leads table holds applicant history and cannot be dropped by rollback.');
    }
};
