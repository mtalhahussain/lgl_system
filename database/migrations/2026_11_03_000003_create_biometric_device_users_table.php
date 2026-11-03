<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('users')->whereNotNull('device_employee_no')->where('device_employee_no', '!=', '')
            ->select('device_employee_no')->groupBy('device_employee_no')->havingRaw('COUNT(*) > 1')->pluck('device_employee_no');
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Users share machine employee numbers ('.$duplicates->implode(', ').'). Give each a unique number, then migrate again.');
        }
        DB::table('users')->where('device_employee_no', '')->update(['device_employee_no' => null]);
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['device_employee_no']);
            $table->unique('device_employee_no');
        });
        Schema::table('biometric_devices', function (Blueprint $table) {
            // Secret for the institute-PC bridge; separate from the machine push token.
            $table->string('bridge_token', 64)->nullable()->unique();
            $table->timestamp('bridge_seen_at')->nullable();
        });
        foreach (DB::table('biometric_devices')->pluck('id') as $id) {
            DB::table('biometric_devices')->where('id', $id)->update(['bridge_token' => Str::random(48)]);
        }
        // Desired state of each employee number on each machine; the bridge applies pending rows.
        Schema::create('biometric_device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_device_id')->constrained()->restrictOnDelete();
            $table->string('employee_no', 64);
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 64)->nullable();
            $table->string('action', 10);
            $table->string('status', 10);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['biometric_device_id', 'employee_no']);
            $table->index(['biometric_device_id', 'status']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Machine sync history must be preserved. Use a reviewed forward change.');
    }
};
