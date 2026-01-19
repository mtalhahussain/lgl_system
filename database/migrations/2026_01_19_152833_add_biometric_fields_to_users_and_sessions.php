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
            $table->boolean('fingerprint_enrolled')->default(false);
            $table->string('device_employee_no')->nullable();
            $table->timestamp('fingerprint_enrolled_at')->nullable();
            $table->text('fingerprint_data')->nullable();
            $table->boolean('biometric_enabled')->default(false);
        });

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->boolean('biometric_active')->default(false);
            $table->timestamp('biometric_start_time')->nullable();
            $table->timestamp('biometric_end_time')->nullable();
            $table->boolean('auto_absent_enabled')->default(true);
            $table->integer('late_threshold_minutes')->default(15);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('device_synced')->default(false);
            $table->boolean('auto_marked')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->string('device_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'fingerprint_enrolled',
                'device_employee_no', 
                'fingerprint_enrolled_at',
                'fingerprint_data',
                'biometric_enabled'
            ]);
        });

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'biometric_active',
                'biometric_start_time',
                'biometric_end_time', 
                'auto_absent_enabled',
                'late_threshold_minutes'
            ]);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'device_synced',
                'auto_marked',
                'synced_at',
                'device_id'
            ]);
        });
    }
};
