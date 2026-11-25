<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Institute WhatsApp number, set by Admin next to the reminders on/off switch on Settings.
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('settings')->where('key', 'whatsapp_number')->exists()) {
            DB::table('settings')->insert(['key' => 'whatsapp_number', 'value' => null, 'type' => 'text', 'group' => 'whatsapp',
                'label' => 'Institute WhatsApp number', 'is_public' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'whatsapp_number')->delete();
    }
};
