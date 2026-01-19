<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\FeeInstallment;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\TeacherEarning;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $reportTypes = [
            'financial' => [
                'title' => 'Financial Reports',
                'description' => 'Fee collection, teacher payments, revenue analysis',
                'icon' => 'fas fa-euro-sign',
                'color' => 'success'
            ],
            'student' => [
                'title' => 'Student Reports',
                'description' => 'Enrollment, attendance, progress tracking',
                'icon' => 'fas fa-users',
                'color' => 'info'
            ],
            'teacher' => [
                'title' => 'Teacher Reports',
                'description' => 'Performance, earnings, class statistics',
                'icon' => 'fas fa-chalkboard-teacher',
                'color' => 'warning'
            ],
            'course' => [
                'title' => 'Course Reports',
                'description' => 'Batch performance, completion rates',
                'icon' => 'fas fa-book',
                'color' => 'primary'
            ],
            'attendance' => [
                'title' => 'Attendance Reports',
                'description' => 'Class attendance, trends, patterns',
                'icon' => 'fas fa-clipboard-check',
                'color' => 'danger'
            ]
        ];

        return view('admin.reports.index', compact('reportTypes'));
    }

    public function generate(Request $request, $type)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());
        
        switch ($type) {
            case 'financial':
                return $this->financialReport($startDate, $endDate);
            case 'student':
                return $this->studentReport($startDate, $endDate);
            case 'teacher':
                return $this->teacherReport($startDate, $endDate);
            case 'course':
                return $this->courseReport($startDate, $endDate);
            case 'attendance':
                return $this->attendanceReport($startDate, $endDate);
            default:
                abort(404);
        }
    }

    private function financialReport($startDate, $endDate)
    {
        // Revenue Analysis
        $revenueData = [
            'total_fees_collected' => FeeInstallment::where('status', 'paid')
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->sum('amount_paid'),
            'total_fees_pending' => FeeInstallment::where('status', 'pending')
                ->whereBetween('due_date', [$startDate, $endDate])
                ->sum('amount'),
            'teacher_payments' => TeacherEarning::whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount'),
            'overdue_fees' => FeeInstallment::where('status', 'pending')
                ->where('due_date', '<', now())
                ->sum('amount')
        ];

        // Daily collection trend
        $dailyCollection = FeeInstallment::where('status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->selectRaw('DATE(paid_at) as date, SUM(amount_paid) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Course-wise revenue
        $courseRevenue = DB::table('fee_installments')
            ->join('enrollments', 'fee_installments.enrollment_id', '=', 'enrollments.id')
            ->join('batches', 'enrollments.batch_id', '=', 'batches.id')
            ->join('courses', 'batches.course_id', '=', 'courses.id')
            ->where('fee_installments.status', 'paid')
            ->whereBetween('fee_installments.paid_at', [$startDate, $endDate])
            ->selectRaw('courses.name, courses.level, SUM(fee_installments.amount_paid) as revenue')
            ->groupBy('courses.id', 'courses.name', 'courses.level')
            ->orderByDesc('revenue')
            ->get();

        // Payment method analysis
        $paymentMethods = FeeInstallment::where('status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->selectRaw('payment_method, SUM(amount_paid) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get();

        return view('admin.reports.financial', compact(
            'revenueData', 'dailyCollection', 'courseRevenue', 'paymentMethods', 'startDate', 'endDate'
        ));
    }

    private function studentReport($startDate, $endDate)
    {
        // Student enrollment trends
        $enrollmentTrends = Enrollment::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as enrollments')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Course-wise enrollments
        $courseEnrollments = DB::table('enrollments')
            ->join('batches', 'enrollments.batch_id', '=', 'batches.id')
            ->join('courses', 'batches.course_id', '=', 'courses.id')
            ->whereBetween('enrollments.created_at', [$startDate, $endDate])
            ->selectRaw('courses.name, courses.level, COUNT(enrollments.id) as count')
            ->groupBy('courses.id', 'courses.name', 'courses.level')
            ->orderByDesc('count')
            ->get();

        // Student progress analysis
        $studentProgress = User::where('role', 'student')
            ->with(['enrollments' => function($q) {
                $q->with('batch.course');
            }, 'attendances', 'certificates'])
            ->get()
            ->map(function($student) {
                $totalAttendances = $student->attendances->count();
                $presentAttendances = $student->attendances->where('status', 'present')->count();
                
                return [
                    'student' => $student,
                    'active_enrollments' => $student->enrollments->where('status', 'active')->count(),
                    'completed_courses' => $student->enrollments->where('status', 'completed')->count(),
                    'attendance_rate' => $totalAttendances > 0 ? round(($presentAttendances / $totalAttendances) * 100, 1) : 0,
                    'certificates_earned' => $student->certificates->count()
                ];
            });

        // Top performing students
        $topStudents = $studentProgress->sortByDesc('attendance_rate')->take(10);

        return view('admin.reports.student', compact(
            'enrollmentTrends', 'courseEnrollments', 'studentProgress', 'topStudents', 'startDate', 'endDate'
        ));
    }

    private function teacherReport($startDate, $endDate)
    {
        // Teacher performance metrics
        $teacherPerformance = User::where('role', 'teacher')
            ->with(['taughtBatches', 'teacherEarnings'])
            ->get()
            ->map(function($teacher) use ($startDate, $endDate) {
                $activeBatches = $teacher->taughtBatches->where('status', 'active');
                $totalStudents = $activeBatches->sum(function($batch) {
                    return $batch->enrollments->where('status', 'active')->count();
                });
                $earnings = $teacher->teacherEarnings()
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('amount');
                
                return [
                    'teacher' => $teacher,
                    'active_batches' => $activeBatches->count(),
                    'total_students' => $totalStudents,
                    'period_earnings' => $earnings,
                    'avg_earnings_per_student' => $totalStudents > 0 ? round($earnings / $totalStudents, 2) : 0
                ];
            });

        // Teacher earnings comparison
        $teacherEarnings = TeacherEarning::whereBetween('created_at', [$startDate, $endDate])
            ->join('users', 'teacher_earnings.teacher_id', '=', 'users.id')
            ->selectRaw('users.name, SUM(teacher_earnings.amount) as total_earnings')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_earnings')
            ->get();

        // Class completion rates by teacher
        $classCompletionRates = User::where('role', 'teacher')
            ->with(['taughtBatches.classSessions'])
            ->get()
            ->map(function($teacher) {
                $totalSessions = $teacher->taughtBatches->sum(function($batch) {
                    return $batch->classSessions->count();
                });
                $completedSessions = $teacher->taughtBatches->sum(function($batch) {
                    return $batch->classSessions->where('status', 'completed')->count();
                });
                
                return [
                    'teacher' => $teacher,
                    'completion_rate' => $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 1) : 0
                ];
            })
            ->sortByDesc('completion_rate');

        return view('admin.reports.teacher', compact(
            'teacherPerformance', 'teacherEarnings', 'classCompletionRates', 'startDate', 'endDate'
        ));
    }

    private function courseReport($startDate, $endDate)
    {
        // Course popularity
        $coursePopularity = Course::withCount(['batches as enrollments_count' => function($q) use ($startDate, $endDate) {
            $q->join('enrollments', 'batches.id', '=', 'enrollments.batch_id')
              ->whereBetween('enrollments.created_at', [$startDate, $endDate]);
        }])
        ->orderByDesc('enrollments_count')
        ->get();

        // Batch completion rates
        $batchCompletionRates = Batch::with(['course', 'enrollments'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function($batch) {
                $totalEnrollments = $batch->enrollments->count();
                $completedEnrollments = $batch->enrollments->where('status', 'completed')->count();
                
                return [
                    'batch' => $batch,
                    'completion_rate' => $totalEnrollments > 0 ? round(($completedEnrollments / $totalEnrollments) * 100, 1) : 0
                ];
            })
            ->sortByDesc('completion_rate');

        // Revenue by course level
        $levelRevenue = DB::table('courses')
            ->join('batches', 'courses.id', '=', 'batches.course_id')
            ->join('enrollments', 'batches.id', '=', 'enrollments.batch_id')
            ->join('fee_installments', 'enrollments.id', '=', 'fee_installments.enrollment_id')
            ->where('fee_installments.status', 'paid')
            ->whereBetween('fee_installments.paid_at', [$startDate, $endDate])
            ->selectRaw('courses.level, SUM(fee_installments.amount_paid) as revenue, COUNT(DISTINCT enrollments.id) as enrollments')
            ->groupBy('courses.level')
            ->orderBy('courses.level')
            ->get();

        return view('admin.reports.course', compact(
            'coursePopularity', 'batchCompletionRates', 'levelRevenue', 'startDate', 'endDate'
        ));
    }

    private function attendanceReport($startDate, $endDate)
    {
        // Overall attendance trends
        $attendanceTrends = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->whereBetween('class_sessions.session_date', [$startDate, $endDate])
            ->selectRaw('DATE(class_sessions.session_date) as date, 
                         COUNT(*) as total_attendances,
                         SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) as present_count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                $item->attendance_rate = $item->total_attendances > 0 ? 
                    round(($item->present_count / $item->total_attendances) * 100, 1) : 0;
                return $item;
            });

        // Course-wise attendance
        $courseAttendance = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->join('batches', 'class_sessions.batch_id', '=', 'batches.id')
            ->join('courses', 'batches.course_id', '=', 'courses.id')
            ->whereBetween('class_sessions.session_date', [$startDate, $endDate])
            ->selectRaw('courses.name, courses.level,
                         COUNT(*) as total_attendances,
                         SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) as present_count')
            ->groupBy('courses.id', 'courses.name', 'courses.level')
            ->get()
            ->map(function($item) {
                $item->attendance_rate = $item->total_attendances > 0 ? 
                    round(($item->present_count / $item->total_attendances) * 100, 1) : 0;
                return $item;
            })
            ->sortByDesc('attendance_rate');

        // Students with poor attendance
        $poorAttendanceStudents = User::where('role', 'student')
            ->with(['attendances' => function($q) use ($startDate, $endDate) {
                $q->whereHas('classSession', function($session) use ($startDate, $endDate) {
                    $session->whereBetween('session_date', [$startDate, $endDate]);
                });
            }])
            ->get()
            ->map(function($student) {
                $totalAttendances = $student->attendances->count();
                $presentAttendances = $student->attendances->where('status', 'present')->count();
                $attendanceRate = $totalAttendances > 0 ? round(($presentAttendances / $totalAttendances) * 100, 1) : 0;
                
                return [
                    'student' => $student,
                    'attendance_rate' => $attendanceRate,
                    'total_sessions' => $totalAttendances
                ];
            })
            ->filter(function($item) {
                return $item['attendance_rate'] < 75 && $item['total_sessions'] > 5; // Less than 75% and more than 5 sessions
            })
            ->sortBy('attendance_rate')
            ->values();

        return view('admin.reports.attendance', compact(
            'attendanceTrends', 'courseAttendance', 'poorAttendanceStudents', 'startDate', 'endDate'
        ));
    }
}