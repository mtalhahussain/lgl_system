<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            // Null only for an Admin test message.
            $table->foreignId('student_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('fee_installment_id')->nullable()->constrained()->restrictOnDelete();
            // before_due | due_today | manual | test
            $table->string('kind', 20);
            // before_due:{installment} / due_today:{installment}: each automatic reminder is sent once.
            $table->string('dedupe_key', 80)->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('template', 100);
            $table->json('params');
            // sending | sent | failed
            $table->string('status', 10);
            $table->string('provider_message_id')->nullable();
            $table->string('error', 1000)->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['fee_installment_id', 'kind']);
        });
        $now = now();
        foreach ([
            ['whatsapp_reminders_enabled', '0', 'boolean', 'Send automatic WhatsApp fee reminders'],
            ['whatsapp_days_before', '3', 'number', 'Days before the due date'],
            ['whatsapp_send_on_due_date', '1', 'boolean', 'Also send on the due date'],
            ['whatsapp_template_name', 'fee_reminder', 'text', 'Approved template name'],
            ['whatsapp_template_language', 'en', 'text', 'Template language code'],
        ] as [$key, $value, $type, $label]) {
            if (! DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert(['key' => $key, 'value' => $value, 'type' => $type, 'group' => 'whatsapp',
                    'label' => $label, 'is_public' => false, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('WhatsApp message history must be preserved. Use a reviewed forward change.');
    }
};
