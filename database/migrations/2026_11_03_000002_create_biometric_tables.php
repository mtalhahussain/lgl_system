<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // Secret part of the push URL: /biometric/hook/{token}.
            $table->string('webhook_token', 64)->unique();
            $table->string('timezone', 64)->default('Asia/Karachi');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
        Schema::create('biometric_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_device_id')->constrained()->restrictOnDelete();
            $table->string('employee_no', 64);
            // Machine wall-clock time in the app timezone.
            $table->dateTime('punched_at');
            $table->string('direction', 5)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('class_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('result', 20);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['biometric_device_id', 'employee_no', 'punched_at'], 'biometric_punch_unique');
            $table->index('punched_at');
            $table->index(['user_id', 'punched_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->index('device_employee_no');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Biometric punch history must be preserved. Use a reviewed forward change.');
    }
};
