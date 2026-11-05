<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_certificates', function (Blueprint $table) {
            $table->foreignId('student_exam_result_id')->nullable()->after('student_id')
                ->constrained('student_exam_results')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable()->after('issued_at');
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')
                ->constrained('users')->nullOnDelete();
            $table->string('revoke_reason', 1000)->nullable()->after('revoked_by');
        });
    }

    public function down(): void
    {
        Schema::table('student_certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_exam_result_id');
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['revoked_at', 'revoke_reason']);
        });
    }
};
