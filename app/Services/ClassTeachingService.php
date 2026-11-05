<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\ClassSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ClassTeachingService
{
    public function record(ClassSession $session, string $outcome, ?string $reason = null): void
    {
        DB::transaction(function () use ($session, $outcome, $reason) {
            if ($session->makeup_for_id) {
                ClassSession::lockForUpdate()->findOrFail($session->makeup_for_id);
            }
            $session = ClassSession::lockForUpdate()->findOrFail($session->id);
            $this->authorize($session->batch);
            if (! in_array($outcome, ['start', 'taken', 'missed'], true)) {
                throw ValidationException::withMessages(['teaching' => 'Invalid teaching outcome.']);
            }
            if ($session->teaching_status === $outcome) {
                return; // An identical repeat must not replace the original actor/time.
            }
            if ($session->teaching_status || $session->status === 'cancelled' || $session->batch->status === 'cancelled') {
                throw ValidationException::withMessages(['teaching' => 'This class is already recorded or cancelled.']);
            }
            if ($session->session_date->gt(today())) {
                throw ValidationException::withMessages(['teaching' => 'Future classes cannot be marked taken or missed.']);
            }
            if ($outcome === 'missed' && (mb_strlen(trim((string) $reason)) < 5 || mb_strlen((string) $reason) > 1000)) {
                throw ValidationException::withMessages(['reason' => 'Enter a reason of 5–1000 characters.']);
            }
            if ($outcome === 'missed' && $session->attendances()->exists()) {
                throw ValidationException::withMessages(['teaching' => 'This class has student attendance. Review the attendance before recording a missed class.']);
            }
            if ($outcome === 'start') {
                if ($session->teaching_started_at) {
                    return;
                }
                $session->update(['status' => 'ongoing', 'teaching_started_at' => now()]);
            } else {
                $session->update(['teaching_status' => $outcome, 'teaching_recorded_at' => now(),
                    'teaching_recorded_by' => auth()->id(), 'teaching_reason' => $outcome === 'missed' ? trim($reason) : null,
                    'status' => $outcome === 'missed' ? 'cancelled' : 'completed']);
            }
            AuditLog::create(['actor_id' => auth()->id(), 'action' => 'class.'.$outcome,
                'subject_type' => 'class_session', 'subject_id' => $session->id,
                'changes' => ['makeup_for_id' => $session->makeup_for_id, 'reason' => $session->teaching_reason]]);
        });
    }

    public function create(array $data): ClassSession
    {
        return DB::transaction(function () use ($data) {
            $source = empty($data['makeup_for_id']) ? null : ClassSession::lockForUpdate()->findOrFail($data['makeup_for_id']);
            $batch = Batch::lockForUpdate()->findOrFail($data['batch_id']);
            $this->authorize($batch);
            if ($source) {
                $this->authorize($source->batch);
                if ($source->teaching_status !== 'missed' || $source->makeup_for_id || $source->batch_id !== $batch->id || $batch->status === 'cancelled') {
                    throw ValidationException::withMessages(['makeup_for_id' => 'Choose an original missed class in this same non-cancelled batch.']);
                }
                if ($source->makeupClasses()->where(fn ($q) => $q->where('status', '!=', 'cancelled')->orWhere('teaching_status', 'taken'))->exists()) {
                    throw ValidationException::withMessages(['makeup_for_id' => 'A pending or taken makeup already exists. Open that class instead.']);
                }
                if ($batch->classSessions()->whereDate('session_date', $data['session_date'])->whereTime('start_time', $data['start_time'])->where('status', '!=', 'cancelled')->exists()) {
                    throw ValidationException::withMessages(['start_time' => 'This batch already has a class at that date and start time.']);
                }
            }
            $session = ClassSession::create($data);
            if ($source) {
                AuditLog::create(['actor_id' => auth()->id(), 'action' => 'class.makeup_scheduled',
                    'subject_type' => 'class_session', 'subject_id' => $session->id,
                    'changes' => ['makeup_for_id' => $source->id, 'session_date' => $data['session_date']]]);
            }

            return $session;
        });
    }

    private function authorize(Batch $batch): void
    {
        abort_unless(auth()->user()?->is_active && in_array(auth()->user()->role, ['admin', 'teacher'], true), 403);
        Gate::authorize('view', $batch);
    }
}
