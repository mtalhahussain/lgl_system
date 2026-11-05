<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\ClassSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchSessionScheduler
{
    public function generate(Batch $batch, int $actorId): int
    {
        return DB::transaction(function () use ($batch, $actorId) {
            $batch = Batch::lockForUpdate()->findOrFail($batch->id);
            $batch->load('schedules');
            if ($batch->schedules->isEmpty() || ! in_array($batch->status, ['upcoming', 'ongoing'], true)) {
                return 0;
            }
            if (! $batch->end_date) {
                throw ValidationException::withMessages(['end_date' => 'Set an end date to create regular classes from the weekly schedule.']);
            }
            $from = max(today(), $batch->start_date->copy()->startOfDay());
            $to = $batch->end_date->copy()->startOfDay();
            if ($from->diffInDays($to, false) > 1826) {
                throw ValidationException::withMessages(['end_date' => 'Automatic class scheduling supports up to five years. Choose an earlier end date.']);
            }
            // Preserve all existing dates, including cancelled, makeup and attended classes.
            $occupied = ClassSession::where('batch_id', $batch->id)
                ->whereDate('session_date', '>=', $from->toDateString())
                ->pluck('session_date')->map(fn ($date) => Carbon::parse($date)->toDateString())->flip();
            $byDay = $batch->schedules->groupBy('weekday');
            $created = 0;
            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                if ($occupied->has($date->toDateString())) {
                    continue;
                }
                foreach ($byDay->get(strtolower($date->format('D')), collect()) as $slot) {
                    if (++$created > 1000) {
                        throw ValidationException::withMessages(['end_date' => 'At most 1000 regular classes can be created per batch save. Choose a shorter duration.']);
                    }
                    ClassSession::create([
                        'batch_id' => $batch->id, 'session_date' => $date->toDateString(),
                        'start_time' => $slot->getRawOriginal('start_time'), 'end_time' => $slot->getRawOriginal('end_time'),
                        'topic' => $batch->name.' class', 'status' => 'scheduled', 'meeting_link' => $batch->meeting_link,
                    ]);
                }
            }
            if ($created) {
                AuditLog::create(['actor_id' => $actorId, 'action' => 'class_sessions.auto_scheduled',
                    'subject_type' => 'batch', 'subject_id' => $batch->id,
                    'changes' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'created' => $created]]);
            }

            return $created;
        });
    }
}
