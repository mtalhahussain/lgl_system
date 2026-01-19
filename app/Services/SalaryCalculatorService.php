<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\TeacherEarning;
use App\Models\TeacherLeave;
use Carbon\Carbon;

class SalaryCalculatorService
{
    /**
     * Calculate teacher earnings for a specific batch and month
     */
    public function calculateBatchEarnings($teacherId, $batchId, $year, $month)
    {
        $batch = Batch::with('course', 'activeEnrollments')->find($batchId);
        
        if (!$batch || $batch->teacher_id != $teacherId) {
            throw new \Exception('Invalid batch or teacher mismatch');
        }

        // Get active enrollments for the specified month
        $studentsCount = $batch->activeEnrollments()
            ->where(function ($query) use ($year, $month) {
                $query->whereYear('enrollment_date', '<', $year)
                      ->orWhere(function ($q) use ($year, $month) {
                          $q->whereYear('enrollment_date', '=', $year)
                            ->whereMonth('enrollment_date', '<=', $month);
                      });
            })
            ->where(function ($query) use ($year, $month) {
                // Exclude dropped students who left before this month
                $query->whereNull('dropout_date')
                      ->orWhere(function ($q) use ($year, $month) {
                          $q->whereYear('dropout_date', '>', $year)
                            ->orWhere(function ($sq) use ($year, $month) {
                                $sq->whereYear('dropout_date', '=', $year)
                                   ->whereMonth('dropout_date', '>', $month);
                            });
                      });
            })
            ->count();

        $perStudentAmount = $batch->course->teacher_per_student_amount;
        
        // Calculate unpaid leave deductions
        $unpaidLeaveDays = $this->getUnpaidLeaveDays($teacherId, $year, $month);
        $deductionRate = $unpaidLeaveDays > 0 ? $this->calculateDeductionRate($unpaidLeaveDays) : 0;
        
        $grossEarning = $studentsCount * $perStudentAmount;
        $deductionAmount = $grossEarning * ($deductionRate / 100);
        $totalEarning = $grossEarning - $deductionAmount;

        return [
            'teacher_id' => $teacherId,
            'batch_id' => $batchId,
            'year' => $year,
            'month' => $month,
            'students_count' => $studentsCount,
            'per_student_amount' => $perStudentAmount,
            'gross_earning' => $grossEarning,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'deduction_amount' => $deductionAmount,
            'total_earning' => $totalEarning,
        ];
    }

    /**
     * Calculate total monthly earnings for a teacher across all batches
     */
    public function calculateMonthlyEarnings($teacherId, $year, $month)
    {
        $teacher = \App\Models\User::teachers()->findOrFail($teacherId);
        $batches = $teacher->teachingBatches()
            ->where('status', 'ongoing')
            ->whereDate('start_date', '<=', Carbon::create($year, $month)->endOfMonth())
            ->get();

        $totalEarnings = [];
        $grandTotal = 0;
        $totalStudents = 0;

        foreach ($batches as $batch) {
            $earnings = $this->calculateBatchEarnings($teacherId, $batch->id, $year, $month);
            $totalEarnings[] = $earnings;
            $grandTotal += $earnings['total_earning'];
            $totalStudents += $earnings['students_count'];
        }

        return [
            'teacher_id' => $teacherId,
            'teacher_name' => $teacher->name,
            'year' => $year,
            'month' => $month,
            'batch_earnings' => $totalEarnings,
            'total_students' => $totalStudents,
            'grand_total' => $grandTotal,
            'unpaid_leave_days' => $this->getUnpaidLeaveDays($teacherId, $year, $month),
        ];
    }

    /**
     * Generate or update teacher earnings records
     */
    public function generateEarningsRecord($teacherId, $batchId, $year, $month)
    {
        $calculations = $this->calculateBatchEarnings($teacherId, $batchId, $year, $month);

        return TeacherEarning::updateOrCreate(
            [
                'teacher_id' => $teacherId,
                'batch_id' => $batchId,
                'year' => $year,
                'month' => $month,
            ],
            [
                'students_count' => $calculations['students_count'],
                'per_student_amount' => $calculations['per_student_amount'],
                'total_earning' => $calculations['total_earning'],
                'status' => 'pending',
            ]
        );
    }

    /**
     * Process monthly payroll for all teachers
     */
    public function processMonthlyPayroll($year, $month)
    {
        $teachers = \App\Models\User::teachers()->active()->get();
        $processedTeachers = [];

        foreach ($teachers as $teacher) {
            $batches = $teacher->teachingBatches()
                ->where('status', 'ongoing')
                ->whereDate('start_date', '<=', Carbon::create($year, $month)->endOfMonth())
                ->get();

            $teacherTotal = 0;
            $batchEarnings = [];

            foreach ($batches as $batch) {
                $earning = $this->generateEarningsRecord($teacher->id, $batch->id, $year, $month);
                $batchEarnings[] = $earning;
                $teacherTotal += $earning->total_earning;
            }

            if ($teacherTotal > 0) {
                $processedTeachers[] = [
                    'teacher' => $teacher,
                    'total_earning' => $teacherTotal,
                    'batch_earnings' => $batchEarnings,
                ];
            }
        }

        return $processedTeachers;
    }

    /**
     * Calculate unpaid leave days for a teacher in a specific month
     */
    private function getUnpaidLeaveDays($teacherId, $year, $month)
    {
        $startOfMonth = Carbon::create($year, $month, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $unpaidLeaves = TeacherLeave::where('teacher_id', $teacherId)
            ->where('status', 'approved')
            ->where('type', 'unpaid')
            ->where(function ($query) use ($startOfMonth, $endOfMonth) {
                $query->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                      ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth])
                      ->orWhere(function ($q) use ($startOfMonth, $endOfMonth) {
                          $q->where('start_date', '<=', $startOfMonth)
                            ->where('end_date', '>=', $endOfMonth);
                      });
            })
            ->get();

        $totalUnpaidDays = 0;

        foreach ($unpaidLeaves as $leave) {
            $leaveStart = Carbon::parse($leave->start_date)->max($startOfMonth);
            $leaveEnd = Carbon::parse($leave->end_date)->min($endOfMonth);
            $totalUnpaidDays += $leaveStart->diffInDays($leaveEnd) + 1;
        }

        return $totalUnpaidDays;
    }

    /**
     * Calculate deduction rate based on unpaid leave days
     */
    private function calculateDeductionRate($unpaidDays)
    {
        // Assuming 30 days per month, calculate proportional deduction
        $workingDaysInMonth = 30;
        return min(($unpaidDays / $workingDaysInMonth) * 100, 100);
    }

    /**
     * Get teacher earnings summary for a period
     */
    public function getEarningsSummary($teacherId, $startYear, $startMonth, $endYear = null, $endMonth = null)
    {
        $endYear = $endYear ?? $startYear;
        $endMonth = $endMonth ?? $startMonth;

        $earnings = TeacherEarning::where('teacher_id', $teacherId)
            ->where(function ($query) use ($startYear, $startMonth, $endYear, $endMonth) {
                $query->where('year', '>', $startYear)
                      ->orWhere(function ($q) use ($startYear, $startMonth) {
                          $q->where('year', '=', $startYear)
                            ->where('month', '>=', $startMonth);
                      });
            })
            ->where(function ($query) use ($endYear, $endMonth) {
                $query->where('year', '<', $endYear)
                      ->orWhere(function ($q) use ($endYear, $endMonth) {
                          $q->where('year', '=', $endYear)
                            ->where('month', '<=', $endMonth);
                      });
            })
            ->with('batch.course')
            ->get();

        return [
            'total_earning' => $earnings->sum('total_earning'),
            'total_paid' => $earnings->sum('paid_amount'),
            'total_pending' => $earnings->sum('total_earning') - $earnings->sum('paid_amount'),
            'earnings_by_month' => $earnings->groupBy(function ($item) {
                return $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
            }),
        ];
    }
}