<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FeeInstallment;
use Carbon\Carbon;

class FeeCalculatorService
{
    /**
     * Calculate fee structure for a course enrollment
     */
    public function calculateFeeStructure($courseId, $discountPercentage = 0, $installments = 1)
    {
        if (filter_var($installments, FILTER_VALIDATE_INT) === false || $installments < 1 || $installments > 12) {
            throw new \InvalidArgumentException('Installment count must be between 1 and 12.');
        }
        $course = Course::findOrFail($courseId);
        $gross = \App\Support\Money::minor($course->total_fee);
        $percentage = \App\Support\Money::minor((string) $discountPercentage);
        if ($percentage > 10000) {
            throw new \InvalidArgumentException('Discount percentage must be between 0 and 100.');
        }
        $discount = intdiv($gross * $percentage + 5000, 10000);

        return ['course_id' => $courseId, 'original_fee' => \App\Support\Money::decimal($gross),
            'discount_percentage' => $discountPercentage, 'discount_amount' => \App\Support\Money::decimal($discount),
            'final_fee' => \App\Support\Money::decimal($gross - $discount), 'installments' => (int) $installments,
            'installment_amount' => \App\Support\Money::decimal(intdiv($gross - $discount, (int) $installments))];
    }

    /**
     * Generate installment schedule for an enrollment
     */
    public function generateInstallmentSchedule($enrollmentId, $installments = 1, $startDate = null)
    {
        if (filter_var($installments, FILTER_VALIDATE_INT) === false || $installments < 1 || $installments > 12) {
            throw new \InvalidArgumentException('Installment count must be between 1 and 12.');
        }
        $enrollment = Enrollment::findOrFail($enrollmentId);
        $start = $startDate ? Carbon::parse($startDate) : $enrollment->enrollment_date->copy();
        $net = \App\Support\Money::minor($enrollment->total_fee) - \App\Support\Money::minor($enrollment->discount_amount);
        if ($net < 0) {
            throw new \InvalidArgumentException('Discount cannot exceed the agreed fee.');
        }
        $schedule = [];
        for ($index = 0; $index < (int) $installments; $index++) {
            $amount = intdiv($net, (int) $installments) + ($index < $net % (int) $installments ? 1 : 0);
            $schedule[] = ['enrollment_id' => $enrollment->id, 'amount' => \App\Support\Money::decimal($amount),
                'due_date' => $start->copy()->addMonthsNoOverflow($index)->toDateString(), 'status' => 'pending'];
        }

        return $schedule;
    }

    /**
     * Create installment records for an enrollment
     */
    public function createInstallments($enrollmentId, $installments = 1, $startDate = null)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($enrollmentId, $installments, $startDate) {
            $enrollment = Enrollment::lockForUpdate()->findOrFail($enrollmentId);
            if ($enrollment->feeInstallments()->exists()) {
                throw new \InvalidArgumentException('Installments already exist for this enrollment.');
            }
            $schedule = $this->generateInstallmentSchedule($enrollmentId, $installments, $startDate);

            return array_map(fn ($row) => FeeInstallment::create($row), $schedule);
        });
    }

    public function createCustomInstallments(int $enrollmentId, array $schedule): array
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($enrollmentId, $schedule) {
            $enrollment = Enrollment::lockForUpdate()->findOrFail($enrollmentId);
            if ($enrollment->feeInstallments()->exists()) {
                throw new \InvalidArgumentException('Installments already exist for this enrollment.');
            }
            \Illuminate\Support\Facades\Validator::make(['schedule' => $schedule], [
                'schedule' => 'required|array|min:1|max:12', 'schedule.*.amount' => 'required|decimal:0,2|min:0|max:99999999.99',
                'schedule.*.due_date' => 'required|date_format:Y-m-d|after_or_equal:'.$enrollment->enrollment_date->toDateString(),
            ])->validate();
            $net = \App\Support\Money::minor($enrollment->total_fee) - \App\Support\Money::minor($enrollment->discount_amount);
            $sum = array_sum(array_map(fn ($row) => \App\Support\Money::minor((string) $row['amount']), $schedule));
            if ($sum !== $net) {
                throw new \InvalidArgumentException('Custom installment amounts must equal the net agreed fee.');
            }

            return array_map(fn ($row) => FeeInstallment::create(['enrollment_id' => $enrollment->id,
                'amount' => \App\Support\Money::decimal(\App\Support\Money::minor((string) $row['amount'])),
                'due_date' => $row['due_date'], 'status' => 'pending']), $schedule);
        });
    }

    /**
     * Calculate payment summary for an enrollment
     */
    public function getPaymentSummary($enrollmentId)
    {
        $enrollment = Enrollment::with(['feeInstallments', 'batch.course'])->findOrFail($enrollmentId);

        $totalFee = $enrollment->total_fee;
        $discountAmount = $enrollment->discount_amount;
        $finalFee = $totalFee - $discountAmount;
        $paidAmount = $enrollment->paid_amount;
        $remainingAmount = $finalFee - $paidAmount;

        $installments = $enrollment->feeInstallments;
        $pendingInstallments = $installments->where('status', 'pending');
        $overdueInstallments = $pendingInstallments->where('due_date', '<', now()->toDateString());
        $upcomingInstallments = $pendingInstallments->where('due_date', '>=', now()->toDateString());

        return [
            'enrollment_id' => $enrollmentId,
            'student_name' => $enrollment->student->name,
            'course_name' => $enrollment->batch->course->name.' - '.$enrollment->batch->course->level,
            'batch_name' => $enrollment->batch->name,
            'total_fee' => $totalFee,
            'discount_amount' => $discountAmount,
            'final_fee' => $finalFee,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'payment_progress' => $finalFee > 0 ? ($paidAmount / $finalFee) * 100 : 100,
            'total_installments' => $installments->count(),
            'paid_installments' => $installments->where('status', 'paid')->count(),
            'pending_installments' => $pendingInstallments->count(),
            'overdue_installments' => $overdueInstallments->count(),
            'overdue_amount' => $overdueInstallments->sum('amount'),
            'upcoming_installments' => $upcomingInstallments->take(3),
        ];
    }

    /**
     * Process fee payment for an enrollment
     */
    public function processPayment($enrollmentId, $amount, $paymentMethod = 'cash', $transactionRef = null, $submissionKey = null)
    {
        if (! is_string($submissionKey)) {
            throw new \InvalidArgumentException('Payment submission key is required.');
        }

        return app(PaymentService::class)->pay((int) $enrollmentId, (string) $amount, $paymentMethod,
            $submissionKey, auth()->user(), $transactionRef);
    }

    /**
     * Get overdue payments report
     */
    public function getOverduePaymentsReport($days = null)
    {
        $query = FeeInstallment::with(['enrollment.student', 'enrollment.batch.course'])
            ->where('status', 'pending')
            ->where('due_date', '<', now()->toDateString());

        if ($days) {
            $query->where('due_date', '>=', now()->subDays($days)->toDateString());
        }

        $overdueInstallments = $query->orderBy('due_date')->get();

        $summary = [
            'total_overdue_amount' => $overdueInstallments->sum('amount'),
            'total_students' => $overdueInstallments->pluck('enrollment.student_id')->unique()->count(),
            'oldest_overdue_date' => $overdueInstallments->min('due_date'),
            'breakdown_by_course' => [],
            'breakdown_by_age' => [
                '1-7_days' => 0,
                '8-30_days' => 0,
                '31-60_days' => 0,
                '60_plus_days' => 0,
            ],
        ];

        foreach ($overdueInstallments as $installment) {
            $courseName = $installment->enrollment->batch->course->name.' - '.$installment->enrollment->batch->course->level;

            if (! isset($summary['breakdown_by_course'][$courseName])) {
                $summary['breakdown_by_course'][$courseName] = [
                    'amount' => 0,
                    'count' => 0,
                ];
            }

            $summary['breakdown_by_course'][$courseName]['amount'] += $installment->amount;
            $summary['breakdown_by_course'][$courseName]['count']++;

            // Age breakdown
            $daysOverdue = now()->diffInDays(Carbon::parse($installment->due_date));

            if ($daysOverdue <= 7) {
                $summary['breakdown_by_age']['1-7_days'] += $installment->amount;
            } elseif ($daysOverdue <= 30) {
                $summary['breakdown_by_age']['8-30_days'] += $installment->amount;
            } elseif ($daysOverdue <= 60) {
                $summary['breakdown_by_age']['31-60_days'] += $installment->amount;
            } else {
                $summary['breakdown_by_age']['60_plus_days'] += $installment->amount;
            }
        }

        return [
            'summary' => $summary,
            'detailed_list' => $overdueInstallments,
        ];
    }

    /**
     * Apply bulk discount to multiple enrollments
     */
    public function applyBulkDiscount($enrollmentIds, $discountPercentage, $reason = null)
    {
        $enrollments = Enrollment::whereIn('id', $enrollmentIds)->get();
        $results = [];

        foreach ($enrollments as $enrollment) {
            $originalFee = $enrollment->total_fee;
            $discountAmount = ($discountPercentage / 100) * $originalFee;
            $newDiscountAmount = $enrollment->discount_amount + $discountAmount;

            // Ensure discount doesn't exceed total fee
            if ($newDiscountAmount > $originalFee) {
                $discountAmount = $originalFee - $enrollment->discount_amount;
                $newDiscountAmount = $originalFee;
            }

            $enrollment->update([
                'discount_amount' => $newDiscountAmount,
                'notes' => $enrollment->notes."\nBulk discount applied: {$discountPercentage}% - {$reason}",
            ]);

            $results[] = [
                'enrollment_id' => $enrollment->id,
                'student_name' => $enrollment->student->name,
                'original_discount' => $enrollment->discount_amount - $discountAmount,
                'additional_discount' => $discountAmount,
                'new_total_discount' => $newDiscountAmount,
                'new_payable_amount' => $originalFee - $newDiscountAmount,
            ];
        }

        return $results;
    }

    /**
     * Generate fee collection report for a period
     */
    public function getFeeCollectionReport($startDate, $endDate)
    {
        $payments = FeeInstallment::with(['enrollment.student', 'enrollment.batch.course'])
            ->where('status', 'paid')
            ->whereBetween('paid_date', [$startDate, $endDate])
            ->get();

        $totalAmount = $payments->sum('amount');
        $totalTransactions = $payments->count();
        $uniqueStudents = $payments->pluck('enrollment.student_id')->unique()->count();

        $paymentMethods = $payments->groupBy('payment_method')->map(function ($group) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
            ];
        });

        $courseBreakdown = $payments->groupBy(function ($payment) {
            return $payment->enrollment->batch->course->name.' - '.$payment->enrollment->batch->course->level;
        })->map(function ($group) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
            ];
        });

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'total_amount' => $totalAmount,
                'total_transactions' => $totalTransactions,
                'unique_students' => $uniqueStudents,
                'average_payment' => $totalTransactions > 0 ? $totalAmount / $totalTransactions : 0,
            ],
            'payment_methods' => $paymentMethods,
            'course_breakdown' => $courseBreakdown,
            'daily_collection' => $payments->groupBy(function ($payment) {
                return Carbon::parse($payment->paid_date)->format('Y-m-d');
            })->map(function ($group) {
                return $group->sum('amount');
            })->sortKeys(),
        ];
    }
}
