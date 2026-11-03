<?php

namespace App\Biometric;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use App\Models\ClassSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Stores a machine punch and, for a student, marks Present/Late in the class session running at that time.
 * Never overwrites an existing attendance record and never marks anyone absent.
 */
class ClassPunchRecorder
{
    public function record(PunchLog $log, BiometricDevice $device): BiometricPunch
    {
        $code = trim($log->employeeId);
        $time = $log->time->copy();
        $key = ['biometric_device_id' => $device->id, 'employee_no' => $code, 'punched_at' => $time->format('Y-m-d H:i:s')];

        try {
            return DB::transaction(function () use ($log, $device, $code, $time, $key) {
                if ($existing = BiometricPunch::where($key)->first()) {
                    return $existing;
                }
                $user = User::where('device_employee_no', $code)->where('is_active', true)->first();
                [$result, $sessionId] = match (true) {
                    $time->gt(now()->addMinutes(config('biometric.future_tolerance_minutes', 10))) => ['clock_ahead', null],
                    $user === null => ['unknown_code', null],
                    $user->role === 'teacher' => ['teacher', null],
                    $user->role === 'staff' => ['staff', null],
                    $user->role !== 'student' => ['ignored', null],
                    default => $this->markStudent($user, $time, $device),
                };

                return BiometricPunch::create($key + ['direction' => $log->type, 'user_id' => $user?->id,
                    'class_session_id' => $sessionId, 'result' => $result, 'created_at' => now()]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            // The machine re-sent the same event concurrently; the first copy was stored.
            return BiometricPunch::where($key)->firstOrFail();
        }
    }

    /** @return array{0: string, 1: ?int} */
    private function markStudent(User $student, Carbon $time, BiometricDevice $device): array
    {
        $sessions = ClassSession::whereDate('session_date', $time->toDateString())->where('status', '!=', 'cancelled')
            ->whereHas('batch.enrollments', fn ($query) => $query->where('student_id', $student->id))
            ->orderBy('start_time')->lockForUpdate()->get();
        foreach ($sessions as $session) {
            $date = $session->session_date->toDateString();
            $start = Carbon::parse($date.' '.$session->start_time->format('H:i:s'), config('app.timezone'));
            $end = Carbon::parse($date.' '.$session->end_time->format('H:i:s'), config('app.timezone'));
            if ($time->lt($start->copy()->subMinutes(config('biometric.early_minutes', 30))) || $time->gt($end)
                || ! $session->eligibleEnrollments()->where('student_id', $student->id)->exists()) {
                continue;
            }
            if (Attendance::where('class_session_id', $session->id)->where('student_id', $student->id)->exists()) {
                return ['already_marked', $session->id];
            }
            $late = $session->late_threshold_minutes ?? config('biometric.late_minutes', 15);
            $status = $time->gt($start->copy()->addMinutes((int) $late)) ? 'late' : 'present';
            $attendance = new Attendance(['class_session_id' => $session->id, 'student_id' => $student->id,
                'status' => $status, 'check_in_time' => $time->format('H:i:s')]);
            $attendance->forceFill(['auto_marked' => true, 'device_id' => (string) $device->id])->save();
            AuditLog::create(['actor_id' => null, 'action' => 'attendance.device_marked', 'subject_type' => 'attendance',
                'subject_id' => $attendance->id, 'changes' => ['status' => $status, 'punched_at' => $time->format('Y-m-d H:i:s'),
                    'device_id' => $device->id], 'created_at' => now()]);

            return [$status, $session->id];
        }

        return ['no_session', null];
    }
}
