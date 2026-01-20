<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\FeeInstallment;
use App\Models\Attendance;
use App\Services\EnrollmentService;
use App\Services\FeeCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StudentController extends Controller
{
    protected $enrollmentService;
    protected $feeCalculatorService;

    public function __construct(
        EnrollmentService $enrollmentService,
        FeeCalculatorService $feeCalculatorService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin,accountant,teacher')->only(['index', 'create', 'store', 'edit', 'update', 'destroy', 'enroll', 'enrollFingerprint', 'removeFingerprint']);
        $this->enrollmentService = $enrollmentService;
        $this->feeCalculatorService = $feeCalculatorService;
    }

    public function index(Request $request)
    {
        $query = User::where('role', 'student')
            ->with(['enrollments.batch.course', 'feeInstallments'])
            ->orderBy('created_at', 'desc');

        // Filter by teacher's batches for teacher role
        if (auth()->user()->role === 'teacher') {
            $query->whereHas('enrollments.batch', function($q) {
                $q->where('teacher_id', auth()->id());
            });
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('student_id', 'LIKE', "%{$search}%");
            });
        }

        // Filter by course
        if ($request->filled('course_id')) {
            $query->whereHas('enrollments.batch', function($q) use ($request) {
                $q->where('course_id', $request->get('course_id'));
            });
        }

        $students = $query->paginate(15);
        $courses = Course::all();
        
        // Calculate statistics
        $stats = [
            'total' => User::where('role', 'student')->count(),
            'active' => User::where('role', 'student')
                ->whereHas('enrollments', function($q) {
                    $q->where('status', 'active');
                })->count(),
            'pending_fees' => FeeInstallment::where('status', 'pending')
                ->whereHas('enrollment.student')->count(),
            'completed_courses' => Enrollment::where('status', 'completed')->count()
        ];

        return view('admin.students.index', compact('students', 'courses', 'stats'));
    }

    public function create()
    {
        $courses = Course::all();
        $batches = Batch::where('status', 'ongoing')
            ->with('course')
            ->get();
        
        return view('admin.students.create', compact('courses', 'batches'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'date_of_birth' => 'required|date',
            'emergency_contact' => 'required|string|max:255',
            'emergency_phone' => 'required|string|max:20',
            'batch_id' => 'required|exists:batches,id',
            'discount_percentage' => 'nullable|numeric|min:0|max:100'
        ]);

        DB::beginTransaction();
        try {
            // Create student user
            $student = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make('student123'), // Default password
                'role' => 'student',
                'phone' => $request->phone,
                'address' => $request->address,
                'date_of_birth' => $request->date_of_birth,
                'emergency_contact' => $request->emergency_contact,
                'emergency_phone' => $request->emergency_phone,
                'student_id' => 'STU' . str_pad(User::where('role', 'student')->count() + 1, 4, '0', STR_PAD_LEFT)
            ]);

            // Enroll in batch
            $batch = Batch::findOrFail($request->batch_id);
            $enrollment = $this->enrollmentService->enrollStudent(
                $student,
                $batch,
                $request->discount_percentage ?? 0
            );

            DB::commit();
            return redirect()->route('students.index')
                ->with('success', 'Student created and enrolled successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create student: ' . $e->getMessage()]);
        }
    }

    public function show(User $student)
    {
        // Check if user can view this student
        if (auth()->user()->role === 'student' && auth()->id() !== $student->id) {
            abort(403);
        }

        // Check if teacher can view this student (only their assigned students)
        if (auth()->user()->role === 'teacher') {
            $hasAccess = $student->enrollments()
                ->whereHas('batch', function($q) {
                    $q->where('teacher_id', auth()->id());
                })
                ->exists();
            
            if (!$hasAccess) {
                abort(403, 'You can only view students from your assigned batches.');
            }
        }

        $student->load([
            'enrollments.batch.course',
            'enrollments.feeInstallments',
            'attendances.classSession.batch',
            'certificates'
        ]);

        $stats = [
            'total_paid' => $student->feeInstallments()->where('fee_installments.status', 'paid')->sum('amount'),
            'pending_fees' => $student->feeInstallments()->where('fee_installments.status', 'pending')->sum('amount'),
            'attendance_rate' => $this->calculateAttendanceRate($student),
            'courses_completed' => $student->enrollments()->where('status', 'completed')->count()
        ];

        return view('admin.students.show', compact('student', 'stats'));
    }

    public function edit(User $student)
    {
        $courses = Course::all();
        $batches = Batch::where('status', 'ongoing')->with('course')->get();
        
        return view('admin.students.edit', compact('student', 'courses', 'batches'));
    }

    public function update(Request $request, User $student)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $student->id,
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'date_of_birth' => 'required|date',
            'emergency_contact' => 'required|string|max:255',
            'emergency_phone' => 'required|string|max:20'
        ]);

        $student->update($request->only([
            'name', 'email', 'phone', 'address', 'date_of_birth',
            'emergency_contact', 'emergency_phone'
        ]));

        return redirect()->route('students.show', $student)
            ->with('success', 'Student updated successfully!');
    }

    public function destroy(User $student)
    {
        // Check if student has any active enrollments
        if ($student->enrollments()->where('status', 'active')->exists()) {
            return back()->withErrors(['error' => 'Cannot delete student with active enrollments.']);
        }

        $student->delete();
        return redirect()->route('students.index')
            ->with('success', 'Student deleted successfully!');
    }

    public function dashboard()
    {
        $student = auth()->user();
        $student->load([
            'enrollments.batch.course',
            'enrollments.batch.classSessions',
            'enrollments.feeInstallments',
            'attendances.classSession.batch',
            'certificates'
        ]);

        // Calculate comprehensive stats
        $stats = [
            'active_enrollments' => $student->enrollments()->where('status', 'active')->count(),
            'total_paid' => $student->feeInstallments()->where('fee_installments.status', 'paid')->sum('amount'),
            'pending_fees' => $student->feeInstallments()->where('fee_installments.status', 'pending')->sum('amount'),
            'attendance_rate' => $this->calculateAttendanceRate($student),
            'certificates_earned' => $student->certificates()->count(),
            'classes_attended' => $student->attendances()->where('status', 'present')->count(),
            'total_classes' => $student->attendances()->count(),
            'overdue_fees' => $student->feeInstallments()
                ->where('fee_installments.status', 'pending')
                ->where('fee_installments.due_date', '<', now())
                ->sum('amount')
        ];

        // Recent attendance with detailed information
        $recentAttendances = $student->attendances()
            ->with(['classSession.batch.course', 'classSession.batch'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Upcoming fee installments
        $upcomingInstallments = $student->feeInstallments()
            ->where('fee_installments.status', 'pending')
            ->where('fee_installments.due_date', '>=', now())
            ->orderBy('fee_installments.due_date')
            ->take(3)
            ->get();

        // Today's and upcoming classes
        $todayClasses = collect();
        $upcomingClasses = collect();
        
        foreach ($student->enrollments->where('status', 'active') as $enrollment) {
            // Get today's classes
            $todayClassSessions = $enrollment->batch->classSessions()
                ->whereDate('session_date', now())
                ->where('status', '!=', 'cancelled')
                ->with(['batch.course', 'attendances' => function($q) use ($student) {
                    $q->where('student_id', $student->id);
                }])
                ->get();
            $todayClasses = $todayClasses->merge($todayClassSessions);

            // Get upcoming classes (next 7 days)
            $upcomingClassSessions = $enrollment->batch->classSessions()
                ->where('session_date', '>', now())
                ->where('session_date', '<=', now()->addDays(7))
                ->where('status', '!=', 'cancelled')
                ->with('batch.course')
                ->orderBy('session_date')
                ->take(5)
                ->get();
            $upcomingClasses = $upcomingClasses->merge($upcomingClassSessions);
        }

        // Sort collections
        $todayClasses = $todayClasses->sortBy('start_time');
        $upcomingClasses = $upcomingClasses->sortBy('session_date');

        // Attendance summary by course
        $attendanceSummary = [];
        foreach ($student->enrollments->where('status', 'active') as $enrollment) {
            $totalSessions = $enrollment->batch->classSessions()->where('status', 'completed')->count();
            $attendedSessions = $student->attendances()
                ->whereHas('classSession.batch', function($q) use ($enrollment) {
                    $q->where('id', $enrollment->batch_id);
                })
                ->where('status', 'present')
                ->count();
            
            $attendanceSummary[] = [
                'course' => $enrollment->batch->course->name,
                'batch' => $enrollment->batch->name,
                'total_sessions' => $totalSessions,
                'attended_sessions' => $attendedSessions,
                'attendance_rate' => $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100, 1) : 0
            ];
        }

        return view('student.dashboard', compact(
            'student', 
            'stats', 
            'recentAttendances', 
            'upcomingInstallments',
            'todayClasses',
            'upcomingClasses',
            'attendanceSummary'
        ));
    }

    public function enroll(Request $request, User $student)
    {
        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'discount_percentage' => 'nullable|numeric|min:0|max:100'
        ]);

        try {
            $batch = Batch::findOrFail($request->batch_id);
            $enrollment = $this->enrollmentService->enrollStudent(
                $student,
                $batch,
                $request->discount_percentage ?? 0
            );

            return back()->with('success', 'Student enrolled successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    private function calculateAttendanceRate(User $student)
    {
        $totalSessions = $student->attendances()->count();
        if ($totalSessions === 0) return 0;
        
        $presentSessions = $student->attendances()->where('status', 'present')->count();
        return round(($presentSessions / $totalSessions) * 100, 1);
    }
    
    /**
     * Enroll student fingerprint
     */
    public function enrollFingerprint(Request $request, User $student)
    {
        $request->validate([
            'device_employee_no' => 'required|integer|min:1|max:9999'
        ]);
        
        try {
            // Check if device employee number is already taken
            $existingStudent = User::where('device_employee_no', $request->device_employee_no)
                ->where('id', '!=', $student->id)
                ->first();
                
            if ($existingStudent) {
                return response()->json([
                    'success' => false,
                    'message' => "Device employee number {$request->device_employee_no} is already assigned to {$existingStudent->name}"
                ], 422);
            }
            
            $biometricService = app(\App\Services\HikvisionAttendanceService::class);
            $result = $biometricService->registerStudentFingerprint(
                $student, 
                $request->device_employee_no
            );
            
            if ($result['success']) {
                $student->update([
                    'device_employee_no' => $request->device_employee_no,
                    'fingerprint_enrolled' => true
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Student fingerprint enrolled successfully!',
                    'device_employee_no' => $request->device_employee_no
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to enroll fingerprint: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove student fingerprint
     */
    public function removeFingerprint(User $student)
    {
        try {
            if (!$student->fingerprint_enrolled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student fingerprint is not enrolled'
                ], 422);
            }
            
            $biometricService = app(\App\Services\HikvisionAttendanceService::class);
            
            // Try to remove from device (non-blocking)
            try {
                $biometricService->removeStudentFingerprint($student->device_employee_no);
            } catch (\Exception $e) {
                // Log but don't fail the operation
                \Log::warning('Failed to remove fingerprint from device: ' . $e->getMessage());
            }
            
            // Always update database
            $student->update([
                'device_employee_no' => null,
                'fingerprint_enrolled' => false
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Student fingerprint removed successfully!'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove fingerprint: ' . $e->getMessage()
            ], 500);
        }
    }
}