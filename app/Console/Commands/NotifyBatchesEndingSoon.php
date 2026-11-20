<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\NotificationService;
use App\Services\TeacherScheduleService;
use Illuminate\Console\Command;

class NotifyBatchesEndingSoon extends Command
{
    protected $signature = 'batches:ending-alerts {--days=7}';

    protected $description = 'Notify admins once per batch when it ends within a week and its teacher has no next batch yet';

    public function handle(TeacherScheduleService $schedule, NotificationService $notifier): int
    {
        $sent = 0;
        foreach ($schedule->batchesEndingSoon((int) $this->option('days')) as $row) {
            $batch = $row['batch'];
            if ($row['nextBatch'] || ! $row['teacher']
                || AuditLog::where('action', 'batch.ending_alert_sent')->where('subject_type', 'batch')->where('subject_id', $batch->id)->exists()) {
                continue;
            }
            $when = $row['daysLeft'] === 0 ? 'today' : ($row['daysLeft'] === 1 ? 'tomorrow' : "in {$row['daysLeft']} days");
            $notifier->toAdmins([
                'type' => 'batch_ending',
                'title' => "{$row['teacher']->name}'s batch ends {$when}",
                'body' => "{$batch->name} ends on {$batch->end_date->format('d M Y')}."
                    .($row['slots'] ? ' Free slots: '.implode(', ', $row['slots']).'.' : '')
                    .' Assign the next batch.',
                'url' => route('batches.create', ['teacher_id' => $batch->teacher_id, 'course_id' => $batch->course_id]),
                'icon' => 'fa-hourglass-end',
                'level' => 'warning',
                'meta' => ['batch_id' => $batch->id, 'teacher_id' => $batch->teacher_id],
            ]);
            AuditLog::create(['actor_id' => null, 'action' => 'batch.ending_alert_sent', 'subject_type' => 'batch',
                'subject_id' => $batch->id, 'changes' => ['end_date' => $batch->end_date->toDateString()]]);
            $sent++;
        }
        $this->info("Batch ending alerts sent: {$sent}");

        return self::SUCCESS;
    }
}
