<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Batch;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->middleware('auth');
        $this->middleware('role:admin,teacher')->except(['myAttendance', 'studentAttendance']);
        $this->attendanceService = $attendanceService;
    }

    public function index(Request $request)
    {
        $query = ClassSession::with(['batch.course', 'batch.teacher', 'attendances.student']);
        
        // Filter by teacher for teacher role
        if (auth()->user()->role === 'teacher') {
            $query->whereHas('batch', function($q) {
                $q->where('teacher_id', auth()->id());
            });
        }
        
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }
        
        if ($request->filled('date')) {
            $query->whereDate('session_date', $request->date);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sessions = $query->orderBy('session_date', 'desc')->paginate(15);
        
        // Get available batches based on role
        if (auth()->user()->role === 'teacher') {
            $batches = Batch::where('teacher_id', auth()->id())
                ->whereIn('status', ['active', 'ongoing'])
                ->with('course')
                ->get();
        } else {
            $batches = Batch::whereIn('status', ['active', 'ongoing'])->with('course')->get();
        }
        
        $stats = [
            'total_sessions' => ClassSession::count(),
            'completed_sessions' => ClassSession::where('status', 'completed')->count(),
            'upcoming_sessions' => ClassSession::where('status', 'scheduled')
                ->where('session_date', '>=', now())->count(),
            'average_attendance' => $this->calculateAverageAttendance()
        ];

        return view('admin.attendance.index', compact('sessions', 'batches', 'stats'));
    }

    public function mark(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'attendance' => 'required|array',
            'attendance.*.student_id' => 'required|exists:users,id',
            'attendance.*.status' => 'required|in:present,absent,late,excused'
        ]);

        DB::beginTransaction();
        try {
            $session = ClassSession::findOrFail($request->session_id);
            
            foreach ($request->attendance as $attendanceData) {
                Attendance::updateOrCreate([
                    'class_session_id' => $session->id,
                    'student_id' => $attendanceData['student_id']
                ], [
                    'status' => $attendanceData['status'],
                    'notes' => $attendanceData['notes'] ?? null
                ]);
            }

            // Update session status if not already completed
            if ($session->status !== 'completed') {
                $session->update(['status' => 'completed']);
            }

            DB::commit();
            return back()->with('success', 'Attendance marked successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to mark attendance: ' . $e->getMessage()]);
        }
    }

    public function show(ClassSession $session)
    {
        $session->load([
            'batch.course',
            'batch.teacher',
            'batch.enrollments.student',
            'attendances.student'
        ]);

        $enrolledStudents = $session->batch->enrollments()
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->pluck('student');

        $attendanceStats = [
            'total_students' => $enrolledStudents->count(),
            'present' => $session->attendances()->where('status', 'present')->count(),
            'absent' => $session->attendances()->where('status', 'absent')->count(),
            'late' => $session->attendances()->where('status', 'late')->count(),
            'excused' => $session->attendances()->where('status', 'excused')->count()
        ];

        return view('admin.attendance.show', compact('session', 'enrolledStudents', 'attendanceStats'));
    }

    public function create(Request $request)
    {
        $batchId = $request->get('batch_id');
        $date = $request->get('date', now()->format('Y-m-d'));
        
        if (auth()->user()->role === 'teacher') {
            $batches = Batch::where('teacher_id', auth()->id())
                ->whereIn('status', ['active', 'ongoing'])
                ->with('course')
                ->get();
        } else {
            $batches = Batch::whereIn('status', ['active', 'ongoing'])->with('course')->get();
        }

        $selectedBatch = null;
        $students = collect();
        
        if ($batchId) {
            $selectedBatch = Batch::with(['course', 'enrollments.student', 'teacher'])->find($batchId);
            
            // Check if teacher has permission to access this batch
            if ($selectedBatch && auth()->user()->role === 'teacher') {
                if ($selectedBatch->teacher_id !== auth()->id()) {
                    $selectedBatch = null; // Teacher can't access other teacher's batch
                }
            }
            
            if ($selectedBatch) {
                $students = $selectedBatch->enrollments()
                    ->where('status', 'active')
                    ->with('student')
                    ->get()
                    ->pluck('student');
            }
        }

        return view('admin.attendance.create', compact('batches', 'selectedBatch', 'students', 'date'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'session_date' => 'required|date',
            'start_time' => 'required|string',
            'end_time' => 'required|string|after:start_time',
            'topic' => 'required|string|max:255',
            'attendance' => 'array', // Not required, allowing partial attendance
            'attendance.*.student_id' => 'required|exists:users,id',
            'attendance.*.status' => 'required|in:present,absent,late,excused',
            'session_type' => 'string',
            'biometric_enabled' => 'boolean'
        ]);

        DB::beginTransaction();
        try {
            // Create class session
            $session = ClassSession::create([
                'batch_id' => $request->batch_id,
                'session_date' => $request->session_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'topic' => $request->topic,
                'session_type' => $request->session_type ?? 'regular',
                'status' => 'completed',
                'biometric_active' => false,
                'auto_absent_enabled' => $request->has('auto_mark_absent')
            ]);

            // Only process attendance if provided (partial attendance support)
            if ($request->has('attendance') && is_array($request->attendance)) {
                foreach ($request->attendance as $attendanceData) {
                    // Skip if no status selected (partial attendance)
                    if (empty($attendanceData['status'])) {
                        continue;
                    }

                    Attendance::create([
                        'class_session_id' => $session->id,
                        'student_id' => $attendanceData['student_id'],
                        'status' => $attendanceData['status'],
                        'check_in_time' => $attendanceData['check_in_time'] ?? null,
                        'notes' => $attendanceData['notes'] ?? null,
                        'device_synced' => false,
                        'auto_marked' => false
                    ]);
                }
            }

            // If biometric mode enabled, start biometric session
            if ($request->has('biometric_enabled') && $request->biometric_enabled) {
                $biometricService = app(\App\Services\HikvisionAttendanceService::class);
                $biometricResult = $biometricService->startBiometricSession($session->id);
                
                if ($biometricResult['success']) {
                    session()->flash('biometric_session', $session->id);
                    session()->flash('success', 'Attendance session created! Biometric attendance is now active for 30 minutes.');
                } else {
                    session()->flash('warning', 'Session created but biometric activation failed: ' . $biometricResult['message']);
                }
            } else {
                // Auto mark absent for unmarked students if requested
                if ($request->has('auto_mark_absent')) {
                    $this->autoMarkAbsentStudents($session);
                }
            }

            DB::commit();
            return redirect()->route('attendance.index')
                ->with('success', 'Session and attendance recorded successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to record attendance: ' . $e->getMessage()]);
        }
    }

    /**
     * Auto mark absent for students without attendance
     */
    protected function autoMarkAbsentStudents($session)
    {
        $enrolledStudents = $session->batch->enrollments()
            ->where('status', 'active')
            ->with('student')
            ->get();

        $markedStudentIds = $session->attendances->pluck('student_id')->toArray();

        foreach ($enrolledStudents as $enrollment) {
            if (!in_array($enrollment->student_id, $markedStudentIds)) {
                Attendance::create([
                    'class_session_id' => $session->id,
                    'student_id' => $enrollment->student_id,
                    'status' => 'absent',
                    'notes' => 'Auto-marked absent',
                    'auto_marked' => true,
                    'device_synced' => false
                ]);
            }
        }
    }

    public function reports(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());
        
        // Student attendance summary
        $studentAttendance = User::where('role', 'student')
            ->whereHas('enrollments', function($q) {
                $q->where('status', 'active');
            })
            ->with(['attendances' => function($q) use ($startDate, $endDate) {
                $q->whereHas('classSession', function($session) use ($startDate, $endDate) {
                    $session->whereBetween('session_date', [$startDate, $endDate]);
                });
            }])
            ->get()
            ->map(function($student) {
                $totalSessions = $student->attendances->count();
                $presentSessions = $student->attendances->where('status', 'present')->count();
                $lateSessions = $student->attendances->where('status', 'late')->count();
                
                return [
                    'student' => $student,
                    'total_sessions' => $totalSessions,
                    'present' => $presentSessions,
                    'absent' => $student->attendances->where('status', 'absent')->count(),
                    'late' => $lateSessions,
                    'excused' => $student->attendances->where('status', 'excused')->count(),
                    'attendance_rate' => $totalSessions > 0 ? round((($presentSessions + $lateSessions) / $totalSessions) * 100, 1) : 0
                ];
            })
            ->sortByDesc('attendance_rate');

        // Batch-wise attendance
        $batchAttendance = Batch::where('status', 'ongoing')
            ->with(['course', 'classSessions' => function($q) use ($startDate, $endDate) {
                $q->whereBetween('session_date', [$startDate, $endDate])
                  ->with('attendances');
            }])
            ->get()
            ->map(function($batch) {
                $totalSessions = $batch->classSessions->count();
                $totalAttendances = $batch->classSessions->sum(function($session) {
                    return $session->attendances->count();
                });
                $presentAttendances = $batch->classSessions->sum(function($session) {
                    return $session->attendances->where('status', 'present')->count();
                });
                
                return [
                    'batch' => $batch,
                    'total_sessions' => $totalSessions,
                    'average_attendance' => $totalAttendances > 0 ? round(($presentAttendances / $totalAttendances) * 100, 1) : 0
                ];
            });

        return view('admin.attendance.reports', compact('studentAttendance', 'batchAttendance', 'startDate', 'endDate'));
    }

    public function studentAttendance(User $student)
    {
        if (auth()->user()->role === 'student' && auth()->id() !== $student->id) {
            abort(403);
        }

        $student->load([
            'attendances.classSession.batch.course',
            'enrollments.batch.course'
        ]);

        $attendanceStats = [
            'total_sessions' => $student->attendances->count(),
            'present' => $student->attendances->where('status', 'present')->count(),
            'absent' => $student->attendances->where('status', 'absent')->count(),
            'late' => $student->attendances->where('status', 'late')->count(),
            'excused' => $student->attendances->where('status', 'excused')->count()
        ];

        $attendanceStats['attendance_rate'] = $attendanceStats['total_sessions'] > 0 
            ? round((($attendanceStats['present'] + $attendanceStats['late']) / $attendanceStats['total_sessions']) * 100, 1) 
            : 0;

        return view('admin.attendance.student', compact('student', 'attendanceStats'));
    }

    private function calculateAverageAttendance()
    {
        $totalAttendances = Attendance::count();
        if ($totalAttendances === 0) return 0;
        
        $presentAttendances = Attendance::whereIn('status', ['present', 'late'])->count();
        return round(($presentAttendances / $totalAttendances) * 100, 1);
    }
    
    /**
     * Start biometric attendance session
     */
    public function startBiometric(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id'
        ]);

        $biometricService = app(\App\Services\HikvisionAttendanceService::class);
        $result = $biometricService->startBiometricSession($request->session_id);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'session_id' => $request->session_id,
                'biometric_window' => 30
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }

    /**
     * End biometric attendance session
     */
    public function endBiometric(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id'
        ]);

        $biometricService = app(\App\Services\HikvisionAttendanceService::class);
        $result = $biometricService->endBiometricSession($request->session_id);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'synced_records' => $result['synced_records'],
                'absent_marked' => $result['absent_marked']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }

    /**
     * Sync attendance from biometric device
     */
    public function syncBiometric(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time'
        ]);

        $biometricService = app(\App\Services\HikvisionAttendanceService::class);
        $result = $biometricService->syncAttendanceFromDevice(
            $request->session_id,
            $request->start_time,
            $request->end_time
        );

        if ($result['success']) {
            // Process synced records
            $session = ClassSession::find($request->session_id);
            $processedCount = 0;

            foreach ($result['records'] as $record) {
                Attendance::updateOrCreate(
                    [
                        'class_session_id' => $request->session_id,
                        'student_id' => $record['student_id']
                    ],
                    [
                        'status' => $record['status'],
                        'check_in_time' => $record['check_in_time'],
                        'device_synced' => true,
                        'notes' => 'Biometric check-in',
                        'synced_at' => now()
                    ]
                );
                $processedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Synced {$processedCount} attendance records",
                'records' => $result['records']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }

    /**
     * Test biometric device connection
     */
    public function testDevice()
    {
        $biometricService = app(\App\Services\HikvisionAttendanceService::class);
        $result = $biometricService->testConnection();

        return response()->json($result);
    }

    /**
     * Auto mark absent students
     */
    public function autoMarkAbsent(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:class_sessions,id'
        ]);

        $biometricService = app(\App\Services\HikvisionAttendanceService::class);
        $result = $biometricService->markAbsentStudents($request->session_id);

        return response()->json($result);
    }

    public function myAttendance(Request $request)
    {
        $student = auth()->user();
        
        // Get student's attendance records with pagination
        $query = $student->attendances()
            ->with(['classSession.batch.course', 'classSession.batch'])
            ->orderBy('created_at', 'desc');

        // Filter by course if requested
        if ($request->filled('course_id')) {
            $query->whereHas('classSession.batch', function($q) use ($request) {
                $q->where('course_id', $request->course_id);
            });
        }

        // Filter by month if requested
        if ($request->filled('month')) {
            $query->whereMonth('created_at', $request->month);
        }

        // Filter by year if requested
        if ($request->filled('year')) {
            $query->whereYear('created_at', $request->year);
        }

        $attendances = $query->paginate(20);

        // Get available courses for filtering
        $courses = $student->enrollments()
            ->with('batch.course')
            ->get()
            ->pluck('batch.course')
            ->unique('id')
            ->values();

        // Calculate detailed statistics
        $stats = [
            'total_classes' => $student->attendances()->count(),
            'present_classes' => $student->attendances()->where('status', 'present')->count(),
            'late_classes' => $student->attendances()->where('status', 'late')->count(),
            'absent_classes' => $student->attendances()->where('status', 'absent')->count(),
            'excused_classes' => $student->attendances()->where('status', 'excused')->count(),
        ];

        $stats['attendance_rate'] = $stats['total_classes'] > 0 
            ? round((($stats['present_classes'] + $stats['late_classes']) / $stats['total_classes']) * 100, 1)
            : 0;

        // Monthly attendance breakdown
        $monthlyStats = $student->attendances()
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late,
                        SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent')
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at) DESC, MONTH(created_at) DESC')
            ->take(6)
            ->get();

        return view('student.attendance', compact('attendances', 'courses', 'stats', 'monthlyStats'));
    }
}