<?php

namespace App\Services;

use App\Models\User;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\TeacherEarning;
use Carbon\Carbon;

class SalaryCalculatorService
{
    /**
     * Calculate teacher salary based on their salary type
     *
     * @param User $teacher
     * @param Carbon $month
     * @return array
     */
    public function calculateTeacherSalary(User $teacher, Carbon $month = null): array
    {
        if (!$month) {
            $month = Carbon::now();
        }

        $result = [
            'teacher_id' => $teacher->id,
            'teacher_name' => $teacher->name,
            'salary_type' => $teacher->salary_type,
            'month' => $month->format('Y-m'),
            'base_amount' => 0,
            'bonus' => 0,
            'deductions' => 0,
            'total_amount' => 0,
            'details' => [],
            'batches_info' => []
        ];

        switch ($teacher->salary_type) {
            case 'monthly':
                $result = $this->calculateMonthlySalary($teacher, $month, $result);
                break;
                
            case 'per_batch':
                $result = $this->calculatePerBatchSalary($teacher, $month, $result);
                break;
                
            case 'per_student':
                $result = $this->calculatePerStudentSalary($teacher, $month, $result);
                break;
                
            default:
                throw new \InvalidArgumentException('Invalid salary type: ' . $teacher->salary_type);
        }

        $result['total_amount'] = $result['base_amount'] + $result['bonus'] - $result['deductions'];

        return $result;
    }

    /**
     * Calculate monthly fixed salary
     */
    private function calculateMonthlySalary(User $teacher, Carbon $month, array $result): array
    {
        $result['base_amount'] = $teacher->monthly_salary ?? 0;
        $result['details'][] = [
            'type' => 'monthly_salary',
            'description' => 'Fixed monthly salary',
            'amount' => $result['base_amount']
        ];

        // Get active batches for information (if teachingBatches relationship exists)
        if (method_exists($teacher, 'teachingBatches')) {
            $activeBatches = $teacher->teachingBatches()
                ->where('status', 'active')
                ->with(['course', 'enrollments' => function($q) {
                    $q->where('status', 'active');
                }])
                ->get();

            foreach ($activeBatches as $batch) {
                $result['batches_info'][] = [
                    'batch_id' => $batch->id,
                    'course_name' => $batch->course->name,
                    'student_count' => $batch->enrollments->count(),
                    'contribution' => 'Fixed monthly salary - not batch dependent'
                ];
            }
        }

        return $result;
    }

    /**
     * Calculate per batch salary
     */
    private function calculatePerBatchSalary(User $teacher, Carbon $month, array $result): array
    {
        $totalAmount = 0;
        
        // Get batches completed in this month (if teachingBatches relationship exists)
        if (method_exists($teacher, 'teachingBatches')) {
            $completedBatches = $teacher->teachingBatches()
                ->where('status', 'completed')
                ->whereYear('end_date', $month->year)
                ->whereMonth('end_date', $month->month)
                ->with(['course', 'enrollments'])
                ->get();

            foreach ($completedBatches as $batch) {
                $batchAmount = $teacher->per_batch_amount ?? 0;
                $totalAmount += $batchAmount;

                $result['details'][] = [
                    'type' => 'batch_completion',
                    'description' => 'Completed batch: ' . $batch->course->name,
                    'amount' => $batchAmount,
                    'batch_id' => $batch->id
                ];

                $result['batches_info'][] = [
                    'batch_id' => $batch->id,
                    'course_name' => $batch->course->name,
                    'student_count' => $batch->enrollments->count(),
                    'contribution' => "Rs. " . number_format($batchAmount, 2)
                ];
            }
        }

        $result['base_amount'] = $totalAmount;

        return $result;
    }

    /**
     * Calculate per student salary
     */
    private function calculatePerStudentSalary(User $teacher, Carbon $month, array $result): array
    {
        $totalAmount = 0;
        $totalStudents = 0;

        // Get active batches in this month (if teachingBatches relationship exists)
        if (method_exists($teacher, 'teachingBatches')) {
            $activeBatches = $teacher->teachingBatches()
                ->where('status', 'active')
                ->with(['course', 'enrollments' => function($q) {
                    $q->where('status', 'active');
                }])
                ->get();

            foreach ($activeBatches as $batch) {
                $activeStudents = $batch->enrollments->count();
                $batchAmount = $activeStudents * ($teacher->per_student_amount ?? 0);
                $totalAmount += $batchAmount;
                $totalStudents += $activeStudents;

                $result['details'][] = [
                    'type' => 'per_student',
                    'description' => $batch->course->name . ' (' . $activeStudents . ' students)',
                    'amount' => $batchAmount,
                    'batch_id' => $batch->id,
                    'student_count' => $activeStudents
                ];

                $result['batches_info'][] = [
                    'batch_id' => $batch->id,
                    'course_name' => $batch->course->name,
                    'student_count' => $activeStudents,
                    'contribution' => "Rs. " . number_format($batchAmount, 2) . " ({$activeStudents} × " . number_format($teacher->per_student_amount, 2) . ")"
                ];
            }
        }

        $result['base_amount'] = $totalAmount;
        $result['total_students'] = $totalStudents;

        return $result;
    }

    /**
     * Generate teacher earning record
     */
    public function generateTeacherEarning(User $teacher, Carbon $month = null): ?TeacherEarning
    {
        $calculation = $this->calculateTeacherSalary($teacher, $month);

        // Only create earning record if there's a base amount
        if ($calculation['base_amount'] > 0) {
            return TeacherEarning::create([
                'user_id' => $teacher->id,
                'month' => $calculation['month'],
                'base_salary' => $calculation['base_amount'],
                'bonus' => $calculation['bonus'],
                'deductions' => $calculation['deductions'],
                'total_earning' => $calculation['total_amount'],
                'calculation_details' => json_encode($calculation)
            ]);
        }

        return null;
    }

    /**
     * Get salary preview for UI
     */
    public function getSalaryPreview(string $salaryType, float $amount, array $batchData = []): array
    {
        switch ($salaryType) {
            case 'monthly':
                return [
                    'type' => 'Monthly Fixed',
                    'amount' => number_format($amount, 2),
                    'description' => 'Fixed amount paid monthly',
                    'example' => "Teacher will receive Rs. " . number_format($amount, 2) . " every month"
                ];

            case 'per_batch':
                $exampleBatches = count($batchData) > 0 ? count($batchData) : 2;
                return [
                    'type' => 'Per Batch',
                    'amount' => number_format($amount, 2),
                    'description' => 'Amount paid per completed batch',
                    'example' => "If {$exampleBatches} batches completed in a month: Rs. " . 
                               number_format($amount * $exampleBatches, 2)
                ];

            case 'per_student':
                $exampleStudents = 0;
                if (count($batchData) > 0) {
                    foreach ($batchData as $batch) {
                        $exampleStudents += $batch['student_count'] ?? 0;
                    }
                } else {
                    $exampleStudents = 15; // Default example
                }
                
                return [
                    'type' => 'Per Student',
                    'amount' => number_format($amount, 2),
                    'description' => 'Amount paid per student per month',
                    'example' => "With {$exampleStudents} total students: Rs. " . 
                               number_format($amount * $exampleStudents, 2) . " per month"
                ];

            default:
                return [
                    'type' => 'Unknown',
                    'amount' => '0.00',
                    'description' => 'Invalid salary type',
                    'example' => 'Please select a valid salary type'
                ];
        }
    }

    /**
     * Calculate all teachers salaries for a month
     */
    public function calculateAllTeachersSalary(Carbon $month = null): array
    {
        if (!$month) {
            $month = Carbon::now();
        }

        $teachers = User::where('role', 'teacher')
            ->where('is_active', true)
            ->get();

        $results = [];
        foreach ($teachers as $teacher) {
            $results[] = $this->calculateTeacherSalary($teacher, $month);
        }

        return $results;
    }
}