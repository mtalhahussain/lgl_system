<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Salary configuration fields for teachers
            $table->enum('salary_type', ['monthly', 'per_batch', 'per_student'])->default('monthly')->after('is_active');
            $table->decimal('monthly_salary', 10, 2)->nullable()->after('salary_type');
            $table->decimal('per_batch_amount', 10, 2)->nullable()->after('monthly_salary');
            $table->decimal('per_student_amount', 10, 2)->nullable()->after('per_batch_amount');
            
            // Additional teacher information fields
            $table->string('qualification')->nullable()->after('per_student_amount');
            $table->integer('experience_years')->default(0)->after('qualification');
            $table->string('specialization')->nullable()->after('experience_years');
            $table->date('hire_date')->nullable()->after('specialization');
            $table->text('bio')->nullable()->after('hire_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'salary_type',
                'monthly_salary',
                'per_batch_amount',
                'per_student_amount',
                'qualification',
                'experience_years',
                'specialization',
                'hire_date',
                'bio'
            ]);
        });
    }
};
