<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\FeeInstallment;
use App\Models\TeacherEarning;
use App\Models\ClassSession;
use App\Models\Attendance;
use App\Services\SalaryCalculatorService;
use App\Services\FeeCalculatorService;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private $salaryCalculator;
    private $feeCalculator;
    private $attendanceService;

    public function __construct(
        SalaryCalculatorService $salaryCalculator,
        FeeCalculatorService $feeCalculator,
        AttendanceService $attendanceService
    ) {
        $this->salaryCalculator = $salaryCalculator;
        $this->feeCalculator = $feeCalculator;
        $this->attendanceService = $attendanceService;
    }

    /**
     * Admin Dashboard Overview
     */
    public function adminDashboard()
    {
        $this->authorize('viewAny', User::class);

        // Key Statistics
        $stats = [
            'total_students' => User::students()->active()->count(),
            'total_teachers' => User::teachers()->active()->count(),
            'total_batches' => Batch::whereIn('status', ['ongoing', 'upcoming'])->count(),
            'total_courses' => Course::active()->count(),
            'active_enrollments' => Enrollment::where('status', 'active')->count(),
            'monthly_revenue' => FeeInstallment::where('status', 'paid')
                ->whereMonth('paid_date', now()->month)
                ->whereYear('paid_date', now()->year)
                ->sum('amount'),
            'pending_payments' => FeeInstallment::where('status', 'pending')->sum('amount'),
            'overdue_payments' => FeeInstallment::where('status', 'pending')
                ->where('due_date', '<', now())
                ->sum('amount'),
        ];

        // Recent Enrollments (Last 7 days)
        $recentEnrollments = Enrollment::with(['student', 'batch.course'])
            ->where('enrollment_date', '>=', now()->subDays(7))
            ->orderBy('enrollment_date', 'desc')
            ->limit(10)
            ->get();

        // Upcoming Payments (Next 7 days)
        $upcomingPayments = FeeInstallment::with(['enrollment.student', 'enrollment.batch.course'])
            ->where('status', 'pending')
            ->whereBetween('due_date', [now(), now()->addDays(7)])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        // Course Performance
        $coursePerformance = Course::withCount([
            'batches as active_batches_count' => function ($query) {
                $query->whereIn('status', ['ongoing', 'upcoming']);
            }
        ])
        ->with(['batches' => function ($query) {
            $query->withCount('activeEnrollments');
        }])
        ->get()
        ->map(function ($course) {
            $totalEnrollments = $course->batches->sum('active_enrollments_count');
            return [
                'course_name' => $course->name . ' - ' . $course->level,
                'active_batches' => $course->active_batches_count,
                'total_enrollments' => $totalEnrollments,
                'revenue' => $totalEnrollments * $course->total_fee,
            ];
        });

        // Monthly Revenue Trend (Last 6 months)
        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $revenue = FeeInstallment::where('status', 'paid')
                ->whereMonth('paid_date', $date->month)
                ->whereYear('paid_date', $date->year)
                ->sum('amount');
            
            $monthlyRevenue[] = [
                'month' => $date->format('M Y'),
                'revenue' => $revenue,
            ];
        }

        return response()->json([
            'stats' => $stats,
            'recent_enrollments' => $recentEnrollments,
            'upcoming_payments' => $upcomingPayments,
            'course_performance' => $coursePerformance,
            'monthly_revenue_trend' => $monthlyRevenue,
        ]);
    }

    /**
     * Teacher Dashboard
     */
    public function teacherDashboard()
    {
        $teacher = auth()->user();
        
        if ($teacher->role !== 'teacher') {
            abort(403, 'Unauthorized');
        }

        // Teacher's Batches
        $batches = Batch::with(['course', 'activeEnrollments'])
            ->where('teacher_id', $teacher->id)
            ->whereIn('status', ['ongoing', 'upcoming'])
            ->get();

        // Today's Sessions
        $todaySessions = ClassSession::with('batch.course')
            ->whereHas('batch', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })
            ->whereDate('session_date', today())
            ->orderBy('start_time')
            ->get();

        // Recent Attendance Summary
        $attendanceStats = [];
        foreach ($batches as $batch) {
            $stats = $this->attendanceService->getBatchAttendanceReport($batch->id);
            $attendanceStats[] = [
                'batch_name' => $batch->name,
                'course' => $batch->course->name . ' - ' . $batch->course->level,
                'average_attendance' => $stats['overall_stats']['average_attendance_rate'],
                'total_students' => $batch->activeEnrollments->count(),
            ];
        }

        // Monthly Earnings
        $currentMonth = now();
        $monthlyEarnings = $this->salaryCalculator->calculateMonthlyEarnings(
            $teacher->id,
            $currentMonth->year,
            $currentMonth->month
        );

        return response()->json([
            'batches' => $batches,
            'today_sessions' => $todaySessions,
            'attendance_stats' => $attendanceStats,
            'monthly_earnings' => $monthlyEarnings,
        ]);
    }

    /**
     * Student Dashboard
     */
    public function studentDashboard()
    {
        $student = auth()->user();
        
        if ($student->role !== 'student') {
            abort(403, 'Unauthorized');
        }

        // Student's Enrollments
        $enrollments = Enrollment::with(['batch.course', 'batch.teacher'])
            ->where('student_id', $student->id)
            ->whereIn('status', ['active'])
            ->get();

        // Payment Summary
        $paymentSummaries = [];
        foreach ($enrollments as $enrollment) {
            $paymentSummaries[] = $this->feeCalculator->getPaymentSummary($enrollment->id);
        }

        // Upcoming Sessions
        $upcomingSessions = ClassSession::with(['batch.course'])
            ->whereHas('batch.activeEnrollments', function ($query) use ($student) {
                $query->where('student_id', $student->id);
            })
            ->where('session_date', '>=', today())
            ->where('status', 'scheduled')
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit(5)
            ->get();

        // Attendance Summary
        $attendanceReport = [];
        foreach ($enrollments as $enrollment) {
            $attendance = Attendance::getAttendanceStats($student->id, $enrollment->batch_id);
            $attendanceReport[] = [
                'batch_name' => $enrollment->batch->name,
                'course' => $enrollment->batch->course->name . ' - ' . $enrollment->batch->course->level,
                'attendance_rate' => $attendance['attendance_rate'],
                'total_sessions' => $attendance['total'],
                'present' => $attendance['present'],
                'absent' => $attendance['absent'],
            ];
        }

        return response()->json([
            'enrollments' => $enrollments,
            'payment_summaries' => $paymentSummaries,
            'upcoming_sessions' => $upcomingSessions,
            'attendance_report' => $attendanceReport,
        ]);
    }

    /**
     * Financial Reports for Admin/Accountant
     */
    public function financialReports(Request $request)
    {
        $this->authorize('viewAny', FeeInstallment::class);

        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        // Fee Collection Report
        $feeReport = $this->feeCalculator->getFeeCollectionReport($startDate, $endDate);

        // Outstanding Payments
        $outstandingReport = $this->feeCalculator->getOverduePaymentsReport();

        // Teacher Earnings Summary
        $teacherEarnings = TeacherEarning::with('teacher')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->groupBy('teacher_id')
            ->map(function ($earnings, $teacherId) {
                $teacher = $earnings->first()->teacher;
                return [
                    'teacher_name' => $teacher->name,
                    'total_earning' => $earnings->sum('total_earning'),
                    'total_paid' => $earnings->sum('paid_amount'),
                    'pending_amount' => $earnings->sum('total_earning') - $earnings->sum('paid_amount'),
                ];
            });

        return response()->json([
            'fee_collection' => $feeReport,
            'outstanding_payments' => $outstandingReport,
            'teacher_earnings' => $teacherEarnings,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Batch Performance Reports
     */
    public function batchPerformanceReports()
    {
        $this->authorize('viewAny', Batch::class);

        // Batch Utilization
        $batchUtilization = Batch::with(['course', 'teacher'])
            ->whereIn('status', ['ongoing', 'upcoming'])
            ->get()
            ->map(function ($batch) {
                $enrollmentCount = $batch->getCurrentEnrollmentCount();
                $utilizationRate = ($enrollmentCount / $batch->max_students) * 100;
                
                return [
                    'batch_name' => $batch->name,
                    'course' => $batch->course->name . ' - ' . $batch->course->level,
                    'teacher' => $batch->teacher->name,
                    'current_enrollments' => $enrollmentCount,
                    'max_capacity' => $batch->max_students,
                    'utilization_rate' => round($utilizationRate, 2),
                    'available_spots' => $batch->max_students - $enrollmentCount,
                ];
            });

        // Attendance Rates by Batch
        $attendanceRates = Batch::with('course')
            ->whereIn('status', ['ongoing'])
            ->get()
            ->map(function ($batch) {
                $attendanceData = $this->attendanceService->getBatchAttendanceReport($batch->id);
                
                return [
                    'batch_name' => $batch->name,
                    'course' => $batch->course->name . ' - ' . $batch->course->level,
                    'average_attendance_rate' => $attendanceData['overall_stats']['average_attendance_rate'] ?? 0,
                    'total_sessions' => $attendanceData['overall_stats']['total_sessions'] ?? 0,
                    'total_students' => $attendanceData['overall_stats']['total_students'] ?? 0,
                ];
            });

        return response()->json([
            'batch_utilization' => $batchUtilization,
            'attendance_rates' => $attendanceRates,
        ]);
    }

    /**
     * Student Progress Reports
     */
    public function studentProgressReports(Request $request)
    {
        $this->authorize('viewAny', Enrollment::class);

        $batchId = $request->get('batch_id');
        $courseLevel = $request->get('course_level');

        $query = Enrollment::with(['student', 'batch.course']);

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        if ($courseLevel) {
            $query->whereHas('batch.course', function ($q) use ($courseLevel) {
                $q->where('level', $courseLevel);
            });
        }

        $enrollments = $query->where('status', 'active')->get();

        $progressReport = $enrollments->map(function ($enrollment) {
            $attendanceStats = Attendance::getAttendanceStats($enrollment->student_id, $enrollment->batch_id);
            $paymentProgress = ($enrollment->paid_amount / $enrollment->total_fee) * 100;

            return [
                'student_name' => $enrollment->student->name,
                'student_email' => $enrollment->student->email,
                'course' => $enrollment->batch->course->name . ' - ' . $enrollment->batch->course->level,
                'batch_name' => $enrollment->batch->name,
                'enrollment_date' => $enrollment->enrollment_date,
                'attendance_rate' => $attendanceStats['attendance_rate'],
                'payment_progress' => round($paymentProgress, 2),
                'total_fee' => $enrollment->total_fee,
                'paid_amount' => $enrollment->paid_amount,
                'remaining_amount' => $enrollment->total_fee - $enrollment->paid_amount - $enrollment->discount_amount,
            ];
        });

        return response()->json($progressReport);
    }

    /**
     * Key Performance Indicators
     */
    public function kpiMetrics()
    {
        $this->authorize('viewAny', User::class);

        // Student Retention Rate
        $totalEnrollments = Enrollment::count();
        $completedEnrollments = Enrollment::where('status', 'completed')->count();
        $droppedEnrollments = Enrollment::where('status', 'dropped')->count();
        
        $retentionRate = $totalEnrollments > 0 ? (($completedEnrollments / $totalEnrollments) * 100) : 0;
        $dropoutRate = $totalEnrollments > 0 ? (($droppedEnrollments / $totalEnrollments) * 100) : 0;

        // Average Class Attendance
        $avgAttendance = ClassSession::join('attendances', 'class_sessions.id', '=', 'attendances.class_session_id')
            ->where('class_sessions.status', 'completed')
            ->where('attendances.status', 'present')
            ->groupBy('class_sessions.id')
            ->selectRaw('COUNT(attendances.id) as present_count')
            ->get()
            ->avg('present_count');

        // Revenue Growth (Month over Month)
        $currentMonthRevenue = FeeInstallment::where('status', 'paid')
            ->whereMonth('paid_date', now()->month)
            ->whereYear('paid_date', now()->year)
            ->sum('amount');

        $lastMonthRevenue = FeeInstallment::where('status', 'paid')
            ->whereMonth('paid_date', now()->subMonth()->month)
            ->whereYear('paid_date', now()->subMonth()->year)
            ->sum('amount');

        $revenueGrowth = $lastMonthRevenue > 0 ? 
            (($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;

        // Teacher Utilization
        $activeTeachers = User::teachers()->active()->count();
        $teachersWithBatches = User::teachers()
            ->whereHas('teachingBatches', function ($query) {
                $query->whereIn('status', ['ongoing', 'upcoming']);
            })
            ->count();

        $teacherUtilization = $activeTeachers > 0 ? 
            ($teachersWithBatches / $activeTeachers) * 100 : 0;

        return response()->json([
            'student_retention_rate' => round($retentionRate, 2),
            'dropout_rate' => round($dropoutRate, 2),
            'average_attendance_rate' => round($avgAttendance ?? 0, 2),
            'revenue_growth' => round($revenueGrowth, 2),
            'teacher_utilization' => round($teacherUtilization, 2),
            'current_month_revenue' => $currentMonthRevenue,
            'last_month_revenue' => $lastMonthRevenue,
        ]);
    }
}