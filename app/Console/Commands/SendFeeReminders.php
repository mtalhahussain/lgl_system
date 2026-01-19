<?php

namespace App\Console\Commands;

use App\Models\FeeInstallment;
use App\Notifications\FeeReminderNotification;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendFeeReminders extends Command
{
    protected $signature = 'fees:send-reminders {--days=3 : Days before due date to send reminder}';
    protected $description = 'Send fee payment reminders to students';

    public function handle()
    {
        $days = $this->option('days');
        $reminderDate = Carbon::now()->addDays($days)->toDateString();

        $upcomingInstallments = FeeInstallment::with(['enrollment.student', 'enrollment.batch.course'])
            ->where('status', 'pending')
            ->whereDate('due_date', $reminderDate)
            ->get();

        $this->info("Found {$upcomingInstallments->count()} installments due in {$days} days");

        foreach ($upcomingInstallments as $installment) {
            $student = $installment->enrollment->student;
            
            // Send notification
            $student->notify(new FeeReminderNotification($installment, $installment->enrollment));
            
            $courseName = $installment->enrollment->batch->course->name . ' - ' . $installment->enrollment->batch->course->level;
            $this->line("✉ Reminder sent to {$student->name} for {$courseName} - " . currency_format($installment->amount));
        }

        $this->info("✅ Fee reminders sent successfully!");
    }
}