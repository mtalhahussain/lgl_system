<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\Carbon;

class EnrollmentService
{
    /** CEFR order; a package covers 2-4 consecutive levels. */
    public const LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

    private $feeCalculator;

    public function __construct(FeeCalculatorService $feeCalculator)
    {
        $this->feeCalculator = $feeCalculator;
    }

    /**
     * Enroll a student in a batch
     */
    public function enrollStudent($studentId, $batchId, $discountPercentage = 0, $installments = 1, ?array $schedule = null,
        ?int $discountMinor = null, $scheduleStart = null)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($studentId, $batchId, $discountPercentage, $installments, $schedule, $discountMinor, $scheduleStart) {
            $batch = Batch::with('course')->lockForUpdate()->findOrFail($batchId);
            $student = User::students()->where('is_active', true)->findOrFail($studentId);
            if (! in_array($batch->status, ['upcoming', 'ongoing'], true) || ! $batch->course->is_active) {
                throw new \InvalidArgumentException('Batch or course is not open for enrollment.');
            }
            if (! $batch->hasCapacity()) {
                throw new \InvalidArgumentException('Batch is full. No available spots.');
            }
            if (Enrollment::where('batch_id', $batch->id)->where('student_id', $student->id)->exists()) {
                throw new \InvalidArgumentException('Student already has an enrollment in batch "'.$batch->name.'".');
            }
            $structure = $this->feeCalculator->calculateFeeStructure($batch->course_id, $discountPercentage, $schedule === null ? $installments : count($schedule));
            $enrollment = Enrollment::create([
                'batch_id' => $batch->id, 'student_id' => $student->id, 'enrollment_date' => now(),
                'status' => 'active', 'total_fee' => $structure['original_fee'],
                // A package passes its exact allocated discount instead of a percentage.
                'discount_amount' => $discountMinor === null ? $structure['discount_amount'] : \App\Support\Money::decimal($discountMinor),
                'paid_amount' => 0,
            ]);
            if ($schedule === null) {
                $this->feeCalculator->createInstallments($enrollment->id, $installments, $scheduleStart);
            } else {
                $this->feeCalculator->createCustomInstallments($enrollment->id, $schedule);
            }
            \App\Models\AuditLog::create(['actor_id' => auth()->id(), 'action' => 'enrollment.created',
                'subject_type' => 'enrollment', 'subject_id' => $enrollment->id,
                'changes' => ['batch_id' => $batch->id, 'total_fee' => $enrollment->total_fee, 'discount_amount' => $enrollment->discount_amount]]);

            return $enrollment->load(['student', 'batch.course', 'feeInstallments']);
        });
    }

    /**
     * Sell a multi-level package (2-4 consecutive CEFR levels, e.g. A1+A2 or A1 to B2) at an Admin-entered price.
     * The price is split across modules in proportion to their normal fees; the last module takes the exact remainder.
     * Plan "full": the whole price is received now and posted to each module ledger.
     * Plan "installments": each module's share is due in equal monthly installments from that module's start; nothing is posted now.
     */
    public function enrollPackage(int $studentId, array $batchIds, string $price, string $plan, int $installments,
        ?string $method, string $key, User $actor, ?string $reference = null): \App\Models\EnrollmentPackage
    {
        abort_unless($actor->isAdmin() && $actor->is_active, 403);
        $batchIds = array_values($batchIds);
        \Illuminate\Support\Facades\Validator::make(compact('batchIds', 'price', 'plan', 'installments', 'method', 'key', 'reference'), [
            'batchIds' => 'required|array|min:2|max:4', 'batchIds.*' => 'required|integer|distinct',
            'price' => 'required|decimal:0,2|gt:0|max:99999999.99', 'plan' => 'required|in:full,installments',
            'installments' => $plan === 'full' ? 'required|integer|in:1' : 'required|integer|min:2|max:12',
            'method' => $plan === 'full' ? 'required|in:cash,bank,card,jazzcash,easypaisa,online' : 'nullable|prohibited',
            'key' => ['required', 'regex:/^[A-Za-z0-9_-]{16,64}$/D'], 'reference' => 'nullable|string|max:255',
        ], ['batchIds.min' => 'Choose batches for at least two levels.', 'batchIds.*.distinct' => 'Choose a different batch for each level.'])->validate();
        $total = \App\Support\Money::minor($price);
        $payload = hash('sha256', json_encode([$studentId, array_map('intval', $batchIds), $total, $plan, $installments, $method, $reference, $actor->id]));

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($studentId, $batchIds, $total, $plan, $installments, $method, $key, $actor, $reference, $payload) {
                // Batch order matches the transfer lock order; serialize package retries for the same student.
                $locked = Batch::whereIn('id', $batchIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $student = User::lockForUpdate()->findOrFail($studentId);
                $existing = \App\Models\EnrollmentPackage::where('submission_key', $key)->first();
                if ($existing) {
                    return $this->replayPackage($existing, $payload);
                }
                $batches = array_map(fn ($id) => $locked->get((int) $id), $batchIds);
                if (in_array(null, $batches, true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['batch_ids' => 'Selected batch was not found.']);
                }
                $courses = Course::whereIn('id', array_map(fn ($batch) => $batch->course_id, $batches))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($batches as $index => $batch) {
                    $batch->setRelation('course', $courses->get($batch->course_id));
                    if ($index === 0) {
                        continue;
                    }
                    $previous = $batches[$index - 1];
                    $position = array_search($previous->course->level, self::LEVELS, true);
                    $expected = $position === false ? null : (self::LEVELS[$position + 1] ?? null);
                    if ($batch->course->level !== $expected) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['batch_ids' => 'Package levels must follow each other without gaps (for example A1, A2, B1).']);
                    }
                    if (! $previous->end_date || ! $batch->start_date || $batch->start_date->lte($previous->end_date)) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['batch_ids' => "The {$previous->course->level} batch needs an end date and the {$batch->course->level} batch must start after it ends."]);
                    }
                }
                $gross = array_map(fn ($batch) => \App\Support\Money::minor($batch->course->total_fee), $batches);
                $grossTotal = array_sum($gross);
                if (min($gross) <= 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['batch_ids' => 'Every level needs a positive normal course fee.']);
                }
                if ($total > $grossTotal) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['price' => 'Package price cannot be more than the normal fees total ('.\App\Support\Money::decimal($grossTotal).').']);
                }
                $nets = [];
                foreach (array_slice($gross, 0, -1) as $fee) {
                    $nets[] = intdiv($total * $fee + intdiv($grossTotal, 2), $grossTotal);
                }
                $nets[] = $total - array_sum($nets);
                if (min($nets) <= 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['price' => 'Each level must receive a positive share of the package price.']);
                }
                $modules = [];
                foreach ($batches as $index => $batch) {
                    // Reuse admission capacity, duplicate, student and course eligibility checks.
                    $start = $plan === 'full' ? null : max(today(), $batch->start_date->copy())->toDateString();
                    $enrollment = $this->enrollStudent($studentId, $batch->id, 0, $installments, null, $gross[$index] - $nets[$index], $start);
                    $payment = $plan === 'full' ? app(PaymentService::class)->pay($enrollment->id, \App\Support\Money::decimal($nets[$index]),
                        $method, hash('sha256', 'package:'.$key.':'.$index), $actor, $reference) : null;
                    $modules[] = ['enrollment_id' => $enrollment->id, 'payment_id' => $payment?->id,
                        'receipt_number' => $payment?->receipt_number, 'course' => $batch->course->name,
                        'level' => $batch->course->level, 'batch' => $batch->name,
                        'start_date' => $batch->start_date->toDateString(), 'end_date' => $batch->end_date?->toDateString(),
                        'gross' => \App\Support\Money::decimal($gross[$index]),
                        'discount' => $enrollment->discount_amount, 'amount' => \App\Support\Money::decimal($nets[$index]),
                        'installments' => $enrollment->feeInstallments->map(fn ($row) => ['due_date' => $row->due_date->toDateString(), 'amount' => $row->amount])->all()];
                }
                $package = \App\Models\EnrollmentPackage::create(['student_id' => $studentId, 'received_by' => $actor->id,
                    'amount' => \App\Support\Money::decimal($total), 'method' => $plan === 'full' ? $method : 'installments', 'reference' => $reference,
                    'receipt_number' => 'PKG-'.\Illuminate\Support\Str::ulid(), 'submission_key' => $key,
                    'payload_hash' => $payload, 'created_at' => now(),
                    'receipt_details' => ['student_name' => $student->name, 'student_reference' => $student->student_id,
                        'institute_name' => institute_name(), 'currency_code' => \App\Models\Setting::get('currency_code', 'PKR'),
                        'timezone' => config('app.timezone'), 'plan' => $plan, 'installments' => $installments,
                        'gross_total' => \App\Support\Money::decimal($grossTotal), 'discount_total' => \App\Support\Money::decimal($grossTotal - $total),
                        'modules' => $modules]]);
                Enrollment::whereIn('id', array_column($modules, 'enrollment_id'))->update(['enrollment_package_id' => $package->id]);
                \App\Models\AuditLog::create(['actor_id' => $actor->id, 'action' => 'enrollment.package_created',
                    'subject_type' => 'enrollment_package', 'subject_id' => $package->id,
                    'changes' => ['receipt_number' => $package->receipt_number, 'amount' => $package->amount, 'plan' => $plan, 'modules' => $modules]]);

                return $package;
            }, 3);
        } catch (\InvalidArgumentException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages(['package' => $exception->getMessage()]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            $existing = \App\Models\EnrollmentPackage::where('submission_key', $key)->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->replayPackage($existing, $payload);
        }
    }

    private function replayPackage(\App\Models\EnrollmentPackage $package, string $payload): \App\Models\EnrollmentPackage
    {
        if (! hash_equals($package->payload_hash, $payload)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['submission_key' => 'Submission key was already used for different package details.']);
        }

        return $package;
    }

    /**
     * Transfer a student to a different batch
     */
    public function transferStudent($enrollmentId, $newBatchId, $transferDate = null, ?string $correctionReason = null)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($enrollmentId, $newBatchId, $transferDate, $correctionReason) {
            $sourceBatch = Enrollment::findOrFail($enrollmentId)->batch_id;
            $lockedBatches = Batch::whereIn('id', [$sourceBatch, $newBatchId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = Enrollment::lockForUpdate()->findOrFail($enrollmentId);
            $target = $lockedBatches->get($newBatchId);
            if (! $target || $source->batch_id === $target->id || $source->status !== 'active'
                || $source->batch->course_id !== $target->course_id || ! in_array($target->status, ['upcoming', 'ongoing'], true)
                || ! $target->hasCapacity() || Enrollment::where('batch_id', $target->id)->where('student_id', $source->student_id)->exists()) {
                throw new \InvalidArgumentException('Transfer requires an active enrollment, available same-course batch and no prior target enrollment.');
            }
            if (\App\Support\Money::minor($source->paid_amount) !== 0 || $source->payments()->exists()
                || $source->feeInstallments()->whereIn('status', ['paid', 'cancelled'])->exists()) {
                throw new \InvalidArgumentException('Transfers with financial activity are not supported.');
            }
            app(PaymentService::class)->assertReconciled($source);
            if ($correctionReason !== null) {
                if (mb_strlen(trim($correctionReason)) < 5 || mb_strlen(trim($correctionReason)) > 500) {
                    throw new \InvalidArgumentException('Provide a correction reason between 5 and 500 characters.');
                }
                if (! $target->course->is_active || $source->enrollment_package_id || $source->refunds()->exists()
                    || \App\Support\Money::minor($source->refunded_amount ?? 0) !== 0
                    || \App\Models\Attendance::where('student_id', $source->student_id)
                        ->whereHas('classSession', fn ($q) => $q->whereIn('batch_id', [$source->batch_id, $target->id]))->exists()
                    || \App\Models\StudentExamResult::where('student_id', $source->student_id)
                        ->whereHas('batchExam', fn ($q) => $q->whereIn('batch_id', [$source->batch_id, $target->id]))->exists()
                    || \App\Models\StudentCertificate::where('student_id', $source->student_id)
                        ->where('level', $source->batch->course->level)->exists()) {
                    throw new \InvalidArgumentException('Batch correction requires an individual enrollment without attendance, exam results or refunds.');
                }
            }
            $date = $transferDate ? Carbon::parse($transferDate)->startOfDay() : today();
            if ($date->lt($source->enrollment_date) || $date->gt(today())) {
                throw new \InvalidArgumentException('Transfer date must be between admission date and today.');
            }
            $new = Enrollment::create(['student_id' => $source->student_id, 'batch_id' => $target->id,
                'enrollment_date' => $correctionReason !== null ? $source->enrollment_date : $date, 'status' => 'active', 'total_fee' => $source->total_fee,
                'discount_amount' => $source->discount_amount, 'paid_amount' => 0, 'transferred_from_enrollment_id' => $source->id]);
            $source->feeInstallments()->update(['enrollment_id' => $new->id]);
            $source->update(['status' => 'transferred', 'transfer_date' => $date, 'transferred_to_batch_id' => $target->id]);
            \App\Models\AuditLog::create(['actor_id' => auth()->id(), 'action' => 'enrollment.transferred',
                'subject_type' => 'enrollment', 'subject_id' => $source->id, 'changes' => ['target_enrollment_id' => $new->id]]);
            if ($correctionReason !== null) {
                \App\Models\AuditLog::create(['actor_id' => auth()->id(), 'action' => 'enrollment.batch_corrected',
                    'subject_type' => 'enrollment', 'subject_id' => $source->id, 'reason' => trim($correctionReason),
                    'changes' => ['from_batch_id' => $sourceBatch, 'to_batch_id' => $target->id, 'target_enrollment_id' => $new->id]]);
            }

            return $new;
        }, 3);
    }

    /**
     * Drop a student from a batch
     */
    public function dropStudent($enrollmentId, $dropDate = null, $reason = null, $refundAmount = 0)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($enrollmentId, $dropDate, $reason, $refundAmount) {
            $enrollment = Enrollment::lockForUpdate()->findOrFail($enrollmentId);
            $date = $dropDate ? Carbon::parse($dropDate)->startOfDay() : today();
            if ($enrollment->status !== 'active' || $date->lt($enrollment->enrollment_date) || $date->gt(today())) {
                throw new \InvalidArgumentException('Withdrawal requires an active enrollment and a date between admission and today.');
            }
            if ($refundAmount > 0 || strlen(trim((string) $reason)) < 5) {
                throw new \InvalidArgumentException('Provide a withdrawal reason. Automated refunds are not supported.');
            }
            $enrollment->update(['status' => 'dropped', 'dropout_date' => $date, 'dropout_reason' => trim($reason)]);
            \App\Models\AuditLog::create(['actor_id' => auth()->id(), 'action' => 'enrollment.withdrawn',
                'subject_type' => 'enrollment', 'subject_id' => $enrollment->id, 'reason' => trim($reason)]);

            return $enrollment;
        });
    }

    /**
     * Complete a student's course
     */
    public function completeEnrollment($enrollmentId, $completionDate = null)
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);
        $completionDate = $completionDate ? Carbon::parse($completionDate) : now();

        if ($enrollment->status !== 'active') {
            throw new \Exception('Can only complete active enrollments.');
        }

        // Check if there are outstanding payments
        $outstandingAmount = $enrollment->remaining_fee;
        if ($outstandingAmount > 0) {
            throw new \Exception("Cannot complete enrollment with outstanding fee: {$outstandingAmount}");
        }

        $enrollment->update([
            'status' => 'completed',
            'notes' => $enrollment->notes."\nCompleted on {$completionDate->format('Y-m-d')}",
        ]);

        return $enrollment;
    }

    /**
     * Get enrollment statistics
     */
    public function getEnrollmentStats($batchId = null, $startDate = null, $endDate = null)
    {
        $query = Enrollment::query();

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        if ($startDate) {
            $query->whereDate('enrollment_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('enrollment_date', '<=', $endDate);
        }

        $enrollments = $query->get();

        return [
            'total_enrollments' => $enrollments->count(),
            'active_enrollments' => $enrollments->where('status', 'active')->count(),
            'completed_enrollments' => $enrollments->where('status', 'completed')->count(),
            'dropped_enrollments' => $enrollments->where('status', 'dropped')->count(),
            'transferred_enrollments' => $enrollments->where('status', 'transferred')->count(),
            'total_revenue' => $enrollments->sum('paid_amount'),
            'pending_revenue' => $enrollments->sum(function ($enrollment) {
                return $enrollment->remaining_fee;
            }),
            'average_fee_per_enrollment' => $enrollments->avg('total_fee'),
            'completion_rate' => $enrollments->count() > 0 ?
                ($enrollments->where('status', 'completed')->count() / $enrollments->count()) * 100 : 0,
            'dropout_rate' => $enrollments->count() > 0 ?
                ($enrollments->where('status', 'dropped')->count() / $enrollments->count()) * 100 : 0,
        ];
    }

    /**
     * Get batch utilization report
     */
    public function getBatchUtilizationReport($courseId = null, $status = null)
    {
        $query = Batch::with(['course', 'activeEnrollments']);

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $batches = $query->get();

        $report = [];

        foreach ($batches as $batch) {
            $currentEnrollments = $batch->getCurrentEnrollmentCount();
            $utilizationRate = ($currentEnrollments / $batch->max_students) * 100;

            $report[] = [
                'batch_id' => $batch->id,
                'batch_name' => $batch->name,
                'course' => $batch->course->name.' - '.$batch->course->level,
                'teacher' => $batch->teacher->name,
                'max_capacity' => $batch->max_students,
                'current_enrollments' => $currentEnrollments,
                'available_spots' => $batch->available_spots,
                'utilization_rate' => $utilizationRate,
                'status' => $batch->status,
                'start_date' => $batch->start_date,
                'end_date' => $batch->end_date,
            ];
        }

        return [
            'batches' => $report,
            'summary' => [
                'total_batches' => count($report),
                'average_utilization' => count($report) > 0 ?
                    array_sum(array_column($report, 'utilization_rate')) / count($report) : 0,
                'fully_utilized_batches' => count(array_filter($report, function ($batch) {
                    return $batch['utilization_rate'] >= 100;
                })),
                'underutilized_batches' => count(array_filter($report, function ($batch) {
                    return $batch['utilization_rate'] < 80;
                })),
            ],
        ];
    }

    /**
     * Generate attendance report for a student
     */
    public function getStudentAttendanceReport($studentId, $batchId = null)
    {
        $enrollmentsQuery = Enrollment::where('student_id', $studentId);

        if ($batchId) {
            $enrollmentsQuery->where('batch_id', $batchId);
        }

        $enrollments = $enrollmentsQuery->with(['batch.course', 'batch.classeSessions.attendances'])
            ->get();

        $report = [];

        foreach ($enrollments as $enrollment) {
            $attendanceStats = \App\Models\Attendance::getAttendanceStats($studentId, $enrollment->batch_id);

            $report[] = [
                'enrollment_id' => $enrollment->id,
                'batch_name' => $enrollment->batch->name,
                'course' => $enrollment->batch->course->name.' - '.$enrollment->batch->course->level,
                'enrollment_status' => $enrollment->status,
                'attendance_stats' => $attendanceStats,
                'sessions_held' => $enrollment->batch->classeSessions()->where('status', 'completed')->count(),
                'payment_status' => [
                    'total_fee' => $enrollment->total_fee,
                    'paid_amount' => $enrollment->paid_amount,
                    'remaining_amount' => $enrollment->remaining_fee,
                    'payment_progress' => $enrollment->payment_progress_percentage,
                ],
            ];
        }

        return $report;
    }
}
