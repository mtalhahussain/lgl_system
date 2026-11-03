<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\Carbon;

class EnrollmentService
{
    public const PACKAGE_AMOUNT = '50000.00';

    private $feeCalculator;

    public function __construct(FeeCalculatorService $feeCalculator)
    {
        $this->feeCalculator = $feeCalculator;
    }

    /**
     * Enroll a student in a batch
     */
    public function enrollStudent($studentId, $batchId, $discountPercentage = 0, $installments = 1, ?array $schedule = null)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($studentId, $batchId, $discountPercentage, $installments, $schedule) {
            $batch = Batch::with('course')->lockForUpdate()->findOrFail($batchId);
            $student = User::students()->where('is_active', true)->findOrFail($studentId);
            if (! in_array($batch->status, ['upcoming', 'ongoing'], true) || ! $batch->course->is_active) {
                throw new \InvalidArgumentException('Batch or course is not open for enrollment.');
            }
            if (! $batch->hasCapacity()) {
                throw new \InvalidArgumentException('Batch is full. No available spots.');
            }
            if (Enrollment::where('batch_id', $batch->id)->where('student_id', $student->id)->exists()) {
                throw new \InvalidArgumentException('Student already has an enrollment in this batch.');
            }
            $structure = $this->feeCalculator->calculateFeeStructure($batch->course_id, $discountPercentage, $schedule === null ? $installments : count($schedule));
            $enrollment = Enrollment::create([
                'batch_id' => $batch->id, 'student_id' => $student->id, 'enrollment_date' => now(),
                'status' => 'active', 'total_fee' => $structure['original_fee'],
                'discount_amount' => $structure['discount_amount'], 'paid_amount' => 0,
            ]);
            if ($schedule === null) {
                $this->feeCalculator->createInstallments($enrollment->id, $installments);
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
     * Sell an A1+A2 package with full advance payment and two canonical ledgers.
     */
    public function enrollPackage(int $studentId, int $a1BatchId, int $a2BatchId, string $amount, string $method,
        string $key, User $actor, ?string $reference = null): \App\Models\EnrollmentPackage
    {
        abort_unless($actor->isAdmin() && $actor->is_active, 403);
        \Illuminate\Support\Facades\Validator::make(compact('amount', 'method', 'key', 'reference'), [
            'amount' => 'required|decimal:0,2|gt:0', 'method' => 'required|in:cash,bank,card,jazzcash,easypaisa,online',
            'key' => ['required', 'regex:/^[A-Za-z0-9_-]{16,64}$/D'], 'reference' => 'nullable|string|max:255',
        ])->validate();
        $total = \App\Support\Money::minor(self::PACKAGE_AMOUNT);
        if (\App\Models\Setting::get('currency_code', 'PKR') !== 'PKR') {
            throw \Illuminate\Validation\ValidationException::withMessages(['package' => 'The Rs 50,000 package is available only with PKR institute currency.']);
        }
        if (\App\Support\Money::minor($amount) !== $total) {
            throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'The A1 + A2 package requires full payment of Rs 50,000. Installments use normal module-wise admission.']);
        }
        $payload = hash('sha256', json_encode([$studentId, $a1BatchId, $a2BatchId, $total, $method, $reference, $actor->id]));

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($studentId, $a1BatchId, $a2BatchId, $total, $method, $key, $actor, $reference, $payload) {
                // Batch order matches the transfer lock order; serialize package retries for the same student.
                $batches = Batch::whereIn('id', [$a1BatchId, $a2BatchId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $student = User::lockForUpdate()->findOrFail($studentId);
                $existing = \App\Models\EnrollmentPackage::where('submission_key', $key)->first();
                if ($existing) {
                    return $this->replayPackage($existing, $payload);
                }
                $first = $batches->get($a1BatchId);
                $second = $batches->get($a2BatchId);
                if (! $first || ! $second || $first->id === $second->id
                    || ! $first->end_date || ! $second->start_date || $second->start_date->lte($first->end_date)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['a2_batch_id' => 'Select an A1 batch with an end date and an A2 batch starting after it ends.']);
                }
                $courses = Course::whereIn('id', [$first->course_id, $second->course_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $first->setRelation('course', $courses->get($first->course_id));
                $second->setRelation('course', $courses->get($second->course_id));
                if ($first->course->level !== 'A1' || $second->course->level !== 'A2') {
                    throw \Illuminate\Validation\ValidationException::withMessages(['a2_batch_id' => 'The package requires A1 followed by A2.']);
                }
                $gross = [\App\Support\Money::minor($first->course->total_fee), \App\Support\Money::minor($second->course->total_fee)];
                $grossTotal = array_sum($gross);
                if (min($gross) <= 0 || $grossTotal < $total) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['a1_batch_id' => 'Both modules need positive normal fees totaling at least Rs 50,000.']);
                }
                $firstNet = intdiv($total * $gross[0] + intdiv($grossTotal, 2), $grossTotal);
                $nets = [$firstNet, $total - $firstNet];
                if (min($nets) <= 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['a1_batch_id' => 'Each module must receive a positive package allocation.']);
                }
                $modules = [];
                foreach ([$first, $second] as $index => $batch) {
                    // Reuse admission capacity, duplicate, student and course eligibility checks.
                    $enrollment = $this->enrollStudent($studentId, $batch->id);
                    $enrollment->update(['discount_amount' => \App\Support\Money::decimal($gross[$index] - $nets[$index])]);
                    $enrollment->feeInstallments()->sole()->update(['amount' => \App\Support\Money::decimal($nets[$index])]);
                    $payment = app(PaymentService::class)->pay($enrollment->id, \App\Support\Money::decimal($nets[$index]),
                        $method, hash('sha256', 'package:'.$key.':'.$index), $actor, $reference);
                    $modules[] = ['enrollment_id' => $enrollment->id, 'payment_id' => $payment->id,
                        'receipt_number' => $payment->receipt_number, 'course' => $batch->course->name,
                        'level' => $batch->course->level, 'batch' => $batch->name,
                        'start_date' => $batch->start_date->toDateString(), 'end_date' => $batch->end_date?->toDateString(),
                        'gross' => \App\Support\Money::decimal($gross[$index]),
                        'discount' => $enrollment->discount_amount, 'amount' => $payment->amount];
                }
                $package = \App\Models\EnrollmentPackage::create(['student_id' => $studentId, 'received_by' => $actor->id,
                    'amount' => self::PACKAGE_AMOUNT, 'method' => $method, 'reference' => $reference,
                    'receipt_number' => 'PKG-'.\Illuminate\Support\Str::ulid(), 'submission_key' => $key,
                    'payload_hash' => $payload, 'created_at' => now(),
                    'receipt_details' => ['student_name' => $student->name, 'student_reference' => $student->student_id,
                        'institute_name' => institute_name(), 'currency_code' => \App\Models\Setting::get('currency_code', 'PKR'),
                        'timezone' => config('app.timezone'), 'modules' => $modules]]);
                Enrollment::whereIn('id', array_column($modules, 'enrollment_id'))->update(['enrollment_package_id' => $package->id]);
                \App\Models\AuditLog::create(['actor_id' => $actor->id, 'action' => 'enrollment.package_created',
                    'subject_type' => 'enrollment_package', 'subject_id' => $package->id,
                    'changes' => ['receipt_number' => $package->receipt_number, 'amount' => $package->amount, 'modules' => $modules]]);

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
    public function transferStudent($enrollmentId, $newBatchId, $transferDate = null)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($enrollmentId, $newBatchId, $transferDate) {
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
            $date = $transferDate ? Carbon::parse($transferDate)->startOfDay() : today();
            if ($date->lt($source->enrollment_date) || $date->gt(today())) {
                throw new \InvalidArgumentException('Transfer date must be between admission date and today.');
            }
            $new = Enrollment::create(['student_id' => $source->student_id, 'batch_id' => $target->id,
                'enrollment_date' => $date, 'status' => 'active', 'total_fee' => $source->total_fee,
                'discount_amount' => $source->discount_amount, 'paid_amount' => 0, 'transferred_from_enrollment_id' => $source->id]);
            $source->feeInstallments()->update(['enrollment_id' => $new->id]);
            $source->update(['status' => 'transferred', 'transfer_date' => $date, 'transferred_to_batch_id' => $target->id]);
            \App\Models\AuditLog::create(['actor_id' => auth()->id(), 'action' => 'enrollment.transferred',
                'subject_type' => 'enrollment', 'subject_id' => $source->id, 'changes' => ['target_enrollment_id' => $new->id]]);

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
