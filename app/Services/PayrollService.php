<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\TeacherDue;
use App\Models\TeacherPayout;
use App\Models\TeacherPayoutReversal;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public const METHODS = ['cash' => 'Cash', 'bank' => 'Bank', 'cheque' => 'Cheque', 'jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa'];

    /** Creates one salary due per active fixed-salary teacher for the month; existing dues are skipped. */
    public function generateSalaries(string $period, User $actor): array
    {
        $this->assertAdmin($actor);
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $period) || $period > now()->format('Y-m')) {
            throw ValidationException::withMessages(['period' => 'Choose the current or a past month.']);
        }
        $created = 0;
        $skipped = 0;
        $teachers = User::where('role', 'teacher')->where('is_active', true)->where('salary_type', 'monthly')
            ->where('monthly_salary', '>', 0)->orderBy('id')->get(['id', 'name', 'monthly_salary']);
        foreach ($teachers as $teacher) {
            $key = "salary:{$teacher->id}:{$period}";
            if (TeacherDue::where('active_key', $key)->exists()) {
                $skipped++;

                continue;
            }
            $amount = Money::decimal(Money::minor((string) $teacher->monthly_salary));
            try {
                DB::transaction(function () use ($teacher, $period, $key, $amount, $actor) {
                    $due = TeacherDue::create(['teacher_id' => $teacher->id, 'kind' => 'salary', 'period' => $period,
                        'rate' => $amount, 'amount' => $amount, 'active_key' => $key, 'created_by' => $actor->id, 'created_at' => now()]);
                    $this->audit($actor, 'teacher_due.created', $due, ['amount' => $amount, 'period' => $period]);
                }, 3);
                $created++;
            } catch (UniqueConstraintViolationException) {
                $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** One earning per batch for a per-student teacher: active + completed students × course rate. */
    public function generateBatchDue(int $batchId, User $actor): TeacherDue
    {
        $this->assertAdmin($actor);
        try {
            return DB::transaction(function () use ($batchId, $actor) {
                $batch = Batch::lockForUpdate()->with(['course:id,teacher_per_student_amount', 'teacher:id,role,salary_type'])->findOrFail($batchId);
                $rate = Money::minor((string) ($batch->course?->teacher_per_student_amount ?? '0'));
                $count = $batch->enrollments()->whereIn('status', ['active', 'completed'])->count();
                $error = match (true) {
                    ! in_array($batch->status, ['ongoing', 'completed'], true) => 'Only ongoing or completed batches can earn.',
                    $batch->teacher?->role !== 'teacher' || $batch->teacher->salary_type !== 'per_student' => 'The batch teacher is not paid per student.',
                    $rate <= 0 => 'Set the course teacher amount per student first.',
                    $count === 0 => 'The batch has no active or completed students.',
                    TeacherDue::where('active_key', 'batch:'.$batch->id)->exists() => 'This batch already has a teacher earning.',
                    default => null,
                };
                if ($error) {
                    throw ValidationException::withMessages(['batch_id' => $error]);
                }
                $due = TeacherDue::create(['teacher_id' => $batch->teacher_id, 'kind' => 'batch', 'batch_id' => $batch->id,
                    'student_count' => $count, 'rate' => Money::decimal($rate), 'amount' => Money::decimal($rate * $count),
                    'active_key' => 'batch:'.$batch->id, 'created_by' => $actor->id, 'created_at' => now()]);
                $this->audit($actor, 'teacher_due.created', $due, ['amount' => $due->amount, 'batch_id' => $batch->id, 'students' => $count]);

                return $due;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['batch_id' => 'This batch already has a teacher earning.']);
        }
    }

    public function void(TeacherDue $due, User $actor, string $reason): TeacherDue
    {
        $this->assertAdmin($actor);
        $this->assertReason($reason);

        return DB::transaction(function () use ($due, $actor, $reason) {
            $due = TeacherDue::lockForUpdate()->findOrFail($due->id);
            if ($due->voided_at) {
                throw ValidationException::withMessages(['due' => 'This due was already voided.']);
            }
            if ($due->payouts()->posted()->exists()) {
                throw ValidationException::withMessages(['due' => 'Reverse its payouts before voiding this due.']);
            }
            $due->update(['active_key' => null, 'voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => trim($reason)]);
            $this->audit($actor, 'teacher_due.voided', $due, [], trim($reason));

            return $due;
        }, 3);
    }

    public function pay(int $dueId, string $amount, string $method, string $key, User $actor, ?string $reference = null): TeacherPayout
    {
        $this->assertAdmin($actor);
        $minor = Money::minor($amount);
        if ($minor <= 0 || ! array_key_exists($method, self::METHODS) || ! preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $key)) {
            throw ValidationException::withMessages(['amount' => 'A positive amount, supported method and valid submission key are required.']);
        }
        $payload = hash('sha256', json_encode([$dueId, $minor, $method, $reference, $actor->id]));

        try {
            return DB::transaction(function () use ($dueId, $minor, $method, $key, $actor, $reference, $payload) {
                $due = TeacherDue::lockForUpdate()->findOrFail($dueId);
                if ($existing = $this->existingPayout($key, $payload)) {
                    return $existing;
                }
                if ($due->voided_at) {
                    throw ValidationException::withMessages(['amount' => 'This due was voided.']);
                }
                $due->load(['payouts.reversal', 'teacher:id,name,employee_id', 'batch:id,name']);
                if ($minor > $due->outstandingMinor()) {
                    throw ValidationException::withMessages(['amount' => 'Payout exceeds the outstanding amount.']);
                }
                $payout = TeacherPayout::create(['teacher_due_id' => $due->id, 'amount' => Money::decimal($minor),
                    'method' => $method, 'reference' => $reference, 'paid_at' => now(), 'paid_by' => $actor->id,
                    'voucher_number' => 'PAY-'.Str::ulid(), 'submission_key' => $key, 'payload_hash' => $payload, 'created_at' => now(),
                    'slip_details' => ['teacher_name' => $due->teacher->name, 'teacher_reference' => $due->teacher->employee_id,
                        'description' => $due->label(), 'due_amount' => $due->amount,
                        'institute_name' => institute_name(), 'currency_code' => \App\Models\Setting::get('currency_code', 'PKR'),
                        'timezone' => config('app.timezone')]]);
                $this->audit($actor, 'teacher_payout.posted', $payout, ['amount' => $payout->amount, 'voucher_number' => $payout->voucher_number]);

                return $payout;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            return $this->existingPayout($key, $payload) ?? throw $exception;
        }
    }

    public function reverse(TeacherPayout $payout, User $actor, string $reason): TeacherPayoutReversal
    {
        $this->assertAdmin($actor);
        $this->assertReason($reason);

        return DB::transaction(function () use ($payout, $actor, $reason) {
            TeacherDue::lockForUpdate()->findOrFail($payout->teacher_due_id);
            if (TeacherPayoutReversal::where('teacher_payout_id', $payout->id)->exists()) {
                throw ValidationException::withMessages(['payout' => 'Payout was already reversed.']);
            }
            $reversal = TeacherPayoutReversal::create(['teacher_payout_id' => $payout->id, 'reversed_by' => $actor->id,
                'reason' => trim($reason), 'created_at' => now()]);
            $this->audit($actor, 'teacher_payout.reversed', $payout, ['reversal_id' => $reversal->id], trim($reason));

            return $reversal;
        }, 3);
    }

    private function existingPayout(string $key, string $payload): ?TeacherPayout
    {
        $existing = TeacherPayout::where('submission_key', $key)->first();
        if ($existing && ! hash_equals($existing->payload_hash, $payload)) {
            throw ValidationException::withMessages(['submission_key' => 'Submission key was already used for different payout details.']);
        }

        return $existing;
    }

    private function assertAdmin(User $actor): void
    {
        abort_unless($actor->isAdmin() && $actor->is_active, 403);
    }

    private function assertReason(string $reason): void
    {
        if (strlen(trim($reason)) < 5 || strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Provide a reason of 5–1000 characters.']);
        }
    }

    private function audit(User $actor, string $action, $subject, array $changes, ?string $reason = null): void
    {
        AuditLog::create(['actor_id' => $actor->id, 'action' => $action, 'subject_type' => $subject instanceof TeacherDue ? 'teacher_due' : 'teacher_payout',
            'subject_id' => $subject->id, 'reason' => $reason, 'changes' => $changes]);
    }
}
