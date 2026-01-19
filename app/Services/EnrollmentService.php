<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Course;
use App\Models\ClassSession;
use Carbon\Carbon;

class EnrollmentService
{
    private $feeCalculator;

    public function __construct(FeeCalculatorService $feeCalculator)
    {
        $this->feeCalculator = $feeCalculator;
    }

    /**
     * Enroll a student in a batch
     */
    public function enrollStudent($studentId, $batchId, $discountPercentage = 0, $installments = 1)
    {
        $batch = Batch::with('course')->findOrFail($batchId);
        $student = User::students()->findOrFail($studentId);

        // Check if batch has capacity
        if (!$batch->hasCapacity()) {
            throw new \Exception('Batch is full. No available spots.');
        }

        // Check if student is already enrolled in this batch
        $existingEnrollment = Enrollment::where('batch_id', $batchId)
            ->where('student_id', $studentId)
            ->whereIn('status', ['active', 'transferred'])
            ->first();

        if ($existingEnrollment) {
            throw new \Exception('Student is already enrolled in this batch.');
        }

        // Calculate fee structure
        $feeStructure = $this->feeCalculator->calculateFeeStructure(
            $batch->course_id, 
            $discountPercentage, 
            $installments
        );

        // Create enrollment
        $enrollment = Enrollment::create([
            'batch_id' => $batchId,
            'student_id' => $studentId,
            'enrollment_date' => now(),
            'status' => 'active',
            'total_fee' => $feeStructure['original_fee'],
            'discount_amount' => $feeStructure['discount_amount'],
            'paid_amount' => 0,
        ]);

        // Create installment schedule
        $this->feeCalculator->createInstallments($enrollment->id, $installments);

        return $enrollment->load(['student', 'batch.course', 'feeInstallments']);
    }

    /**
     * Transfer a student to a different batch
     */
    public function transferStudent($enrollmentId, $newBatchId, $transferDate = null)
    {
        $enrollment = Enrollment::with(['batch', 'student', 'feeInstallments'])
            ->findOrFail($enrollmentId);
        
        $newBatch = Batch::findOrFail($newBatchId);
        $transferDate = $transferDate ? Carbon::parse($transferDate) : now();

        // Validate transfer
        if ($enrollment->status !== 'active') {
            throw new \Exception('Can only transfer active enrollments.');
        }

        if (!$newBatch->hasCapacity()) {
            throw new \Exception('Target batch is full.');
        }

        if ($enrollment->batch->course_id !== $newBatch->course_id) {
            throw new \Exception('Cannot transfer to a different course.');
        }

        // Update current enrollment
        $enrollment->update([
            'status' => 'transferred',
            'transferred_to_batch_id' => $newBatchId,
            'transfer_date' => $transferDate,
        ]);

        // Create new enrollment in target batch
        $newEnrollment = Enrollment::create([
            'batch_id' => $newBatchId,
            'student_id' => $enrollment->student_id,
            'enrollment_date' => $transferDate,
            'status' => 'active',
            'total_fee' => $enrollment->total_fee,
            'discount_amount' => $enrollment->discount_amount,
            'paid_amount' => $enrollment->paid_amount,
            'notes' => "Transferred from batch: {$enrollment->batch->name}",
        ]);

        // Transfer pending installments to new enrollment
        $pendingInstallments = $enrollment->feeInstallments()
            ->where('status', 'pending')
            ->get();

        foreach ($pendingInstallments as $installment) {
            $installment->update([
                'enrollment_id' => $newEnrollment->id,
                'due_date' => max($installment->due_date, $transferDate->toDateString()),
            ]);
        }

        return $newEnrollment->load(['student', 'batch.course', 'feeInstallments']);
    }

    /**
     * Drop a student from a batch
     */
    public function dropStudent($enrollmentId, $dropDate = null, $reason = null, $refundAmount = 0)
    {
        $enrollment = Enrollment::with(['feeInstallments'])->findOrFail($enrollmentId);
        $dropDate = $dropDate ? Carbon::parse($dropDate) : now();

        if ($enrollment->status !== 'active') {
            throw new \Exception('Can only drop active enrollments.');
        }

        // Cancel pending installments
        $enrollment->feeInstallments()
            ->where('status', 'pending')
            ->where('due_date', '>', $dropDate->toDateString())
            ->update(['status' => 'cancelled']);

        // Process refund if applicable
        if ($refundAmount > 0) {
            $enrollment->decrement('paid_amount', $refundAmount);
        }

        // Update enrollment status
        $enrollment->update([
            'status' => 'dropped',
            'dropout_date' => $dropDate,
            'dropout_reason' => $reason,
            'notes' => $enrollment->notes . "\nDropped on {$dropDate->format('Y-m-d')}. Reason: {$reason}",
        ]);

        return $enrollment;
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
            'notes' => $enrollment->notes . "\nCompleted on {$completionDate->format('Y-m-d')}",
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
                'course' => $batch->course->name . ' - ' . $batch->course->level,
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
                'course' => $enrollment->batch->course->name . ' - ' . $enrollment->batch->course->level,
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