<?php

namespace App\Console\Commands;

use App\Services\FeeReminderService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendFeeReminders extends Command
{
    protected $signature = 'fees:whatsapp-reminders';

    protected $description = 'Send WhatsApp fee reminders for installments due soon or today (each reminder once)';

    public function handle(FeeReminderService $reminders, WhatsAppService $whatsApp): int
    {
        if (! $reminders->settings()['enabled']) {
            $this->warn('WhatsApp reminders are switched off (Admin > WhatsApp Reminders).');

            return self::FAILURE;
        }
        if (! $whatsApp->configured()) {
            $this->error('WhatsApp is not configured. Set WHATSAPP_TOKEN and WHATSAPP_PHONE_NUMBER_ID in .env.');

            return self::FAILURE;
        }
        $totals = $reminders->sendDue();
        $this->info("WhatsApp reminders: {$totals['sent']} sent, {$totals['failed']} failed, {$totals['skipped']} already sent.");

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
