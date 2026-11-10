<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EnrollmentService;
use App\Services\FeeCalculatorService;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    protected $enrollmentService;

    protected $feeCalculatorService;

    public function __construct(
        EnrollmentService $enrollmentService,
        FeeCalculatorService $feeCalculatorService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin,teacher')->only(['index', 'create', 'store', 'edit', 'update', 'destroy', 'enroll', 'enrollFingerprint', 'removeFingerprint']);
        $this->enrollmentService = $enrollmentService;
        $this->feeCalculatorService = $feeCalculatorService;
    }

    public function index(Request $request)
    {
        if (auth()->user()->isTeacher()) {
            $batches = Batch::where('teacher_id', auth()->id())->orderBy('name')->pluck('name', 'id');
            $list = ListQuery::for(User::students()->select('users.id', 'users.name')
                ->whereHas('enrollments.batch', fn ($query) => $query->where('teacher_id', auth()->id())), $request)
                ->search(['name'])
                ->filter('batch_id', fn ($query, $id) => $query->whereHas('enrollments', fn ($enrollment) => $enrollment->where('batch_id', $id)), $batches->keys()->all())
                ->sorts(['name' => 'name'], 'name');
            $students = $list->paginate();

            return view('academic.students', compact('students', 'batches'));
        }
        $courses = Course::orderBy('name')->pluck('name', 'id');
        $batches = Batch::orderBy('name')->pluck('name', 'id');
        $list = ListQuery::for(User::students()->with('enrollments.batch:id,name'), $request)
            ->search(['name', 'email', 'phone'])
            ->filter('status', fn ($query, $status) => $query->where('is_active', $status === 'active'), ['active', 'inactive'])
            ->filter('course_id', fn ($query, $id) => $query->whereHas('enrollments.batch', fn ($batch) => $batch->where('course_id', $id)), $courses->keys()->all())
            ->filter('batch_id', fn ($query, $id) => $query->whereHas('enrollments', fn ($enrollment) => $enrollment->where('batch_id', $id)), $batches->keys()->all())
            ->sorts(['name' => 'name', 'joined' => 'created_at'], '-joined');
        if ($list->wantsCsv()) {
            return $list->csv('students', ['Name' => fn ($user) => $user->name, 'Email' => fn ($user) => $user->email,
                'Phone' => fn ($user) => $user->phone, 'Batches' => fn ($user) => $user->enrollments->pluck('batch.name')->filter()->implode('; '),
                'Status' => fn ($user) => $user->is_active ? 'Active' : 'Inactive', 'Joined' => fn ($user) => $user->created_at?->toDateString()]);
        }
        $counts = $list->counts(['active' => 'users.is_active = 1', 'recent' => ['users.created_at >= ?', [today()->startOfMonth()]]]);
        $kpis = [['label' => 'Students', 'value' => $counts['total'], 'icon' => 'fa-users'],
            ['label' => 'Active', 'value' => $counts['active'], 'icon' => 'fa-user-check'],
            ['label' => 'Inactive', 'value' => $counts['total'] - $counts['active'], 'icon' => 'fa-user-slash'],
            ['label' => 'Joined this month', 'value' => $counts['recent'], 'icon' => 'fa-user-plus']];
        $filters = [['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ['name' => 'course_id', 'label' => 'Course', 'options' => $courses], ['name' => 'batch_id', 'label' => 'Batch', 'options' => $batches]];

        return view('admin.people.index', ['people' => $list->paginate(), 'kind' => 'students', 'kpis' => $kpis, 'filters' => $filters]);
    }

    public function create()
    {
        $courses = Course::all();
        $batches = Batch::where('status', 'ongoing')
            ->with('course')
            ->get();

        return view('admin.people.form', ['kind' => 'students', 'person' => null, 'batches' => $batches]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|regex:/^\+?[0-9]+$/D',
            'address' => 'required|string|max:500',
            'date_of_birth' => 'nullable|date|before:today',
            'emergency_contact' => 'nullable|string|max:20|regex:/^\+?[0-9]+$/D',
            'password' => 'nullable|required_with:password_confirmation|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
            'batch_id' => 'nullable|exists:batches,id',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();
        try {
            // Create student user
            $student = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->filled('password') ? $request->password : Str::random(64)),
                'role' => 'student',
                'phone' => $request->phone,
                'address' => $request->address,
                'date_of_birth' => $request->date_of_birth,
                'emergency_contact' => $request->emergency_contact,
                'is_active' => $request->boolean('is_active', true),
            ]);

            // Enroll in batch
            if ($request->filled('batch_id')) {
                $batch = Batch::findOrFail($request->batch_id);
                $enrollment = $this->enrollmentService->enrollStudent(
                    $student->id,
                    $batch->id,
                    $request->discount_percentage ?? 0
                );
            }

            DB::commit();

            return redirect()->route('students.index')
                ->with('success', 'Student created and enrolled successfully!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Failed to create student: '.$e->getMessage()]);
        }
    }

    public function show(User $student)
    {
        abort_unless($student->isStudent(), 404);
        $this->authorize('view', $student);

        if (auth()->user()->isTeacher()) {
            $student = User::select('id', 'name')->findOrFail($student->id);
            $enrollments = $student->enrollments()->select('id', 'batch_id', 'student_id', 'enrollment_date', 'status')
                ->whereHas('batch', fn ($query) => $query->where('teacher_id', auth()->id()))
                ->with('batch:id,name')->get();

            return view('academic.student', compact('student', 'enrollments'));
        }
        // Check if user can view this student
        if (auth()->user()->role === 'student' && auth()->id() !== $student->id) {
            abort(403);
        }

        // Check if teacher can view this student (only their assigned students)
        if (auth()->user()->role === 'teacher') {
            $hasAccess = $student->enrollments()
                ->whereHas('batch', function ($q) {
                    $q->where('teacher_id', auth()->id());
                })
                ->exists();

            if (! $hasAccess) {
                abort(403, 'You can only view students from your assigned batches.');
            }
        }

        $student->load('enrollments.batch.course');

        return view('admin.people.profile', ['person' => $student, 'kind' => 'students', 'enrollments' => $student->enrollments, 'batches' => collect()]);
    }

    public function edit(User $student)
    {
        abort_unless($student->isStudent(), 404);
        $courses = Course::all();
        $batches = Batch::where('status', 'ongoing')->with('course')->get();

        return view('admin.people.form', ['kind' => 'students', 'person' => $student, 'batches' => $batches]);
    }

    public function update(Request $request, User $student)
    {
        abort_unless($student->isStudent(), 404);
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$student->id,
            'phone' => 'required|string|max:20|regex:/^\+?[0-9]+$/D',
            'address' => 'required|string|max:500',
            'date_of_birth' => 'nullable|date|before:today',
            'emergency_contact' => 'nullable|string|max:20|regex:/^\+?[0-9]+$/D',
            'password' => 'nullable|required_with:password_confirmation|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($request, $student) {
            $student->update($request->only([
                'name', 'email', 'phone', 'address', 'date_of_birth',
                'emergency_contact', 'is_active',
            ]));
            if ($request->filled('password')) {
                $student->update(['password' => $request->password]);
            }
        });

        return redirect()->route('students.show', $student)
            ->with('success', 'Student updated successfully!');
    }

    public function destroy(User $student)
    {
        abort_unless($student->isStudent(), 404);
        DB::transaction(fn () => $student->update(['is_active' => false]));

        return redirect()->route('students.index')
            ->with('success', 'Student deactivated. Enrollment and financial history preserved.');
    }

    public function dashboard()
    {
        $enrollments = auth()->user()->enrollments()->with('batch.course:id,name,level')->orderByDesc('id')->get();
        $sessions = ClassSession::whereHas('batch.enrollments', fn ($query) => $query->where('student_id', auth()->id())->where('status', 'active'))
            ->whereIn('status', ['scheduled', 'ongoing'])->whereDate('session_date', '>=', today())->with('batch:id,name,meeting_link,meeting_platform,status')->orderBy('session_date')->orderBy('start_time')->limit(10)->get();
        $attendance = Attendance::getAttendanceStats(auth()->id());
        $unmarked = max(0, app(AttendanceService::class)->expectedStudentSessions(auth()->id()) - $attendance['total']);

        return view('dashboards.student', compact('enrollments', 'sessions', 'attendance', 'unmarked'));
    }

    public function timetable()
    {
        $student = auth()->user();
        $batches = Batch::whereHas('enrollments', fn ($query) => $query->where('student_id', $student->id)->where('status', 'active'))
            ->whereIn('status', ['upcoming', 'ongoing'])
            ->with(['course:id,name,level', 'teacher:id,name', 'schedules'])
            ->orderBy('name')->get();
        $sessions = ClassSession::whereHas('batch.enrollments', fn ($query) => $query->where('student_id', $student->id)->where('status', 'active'))
            ->where('status', 'scheduled')->whereDate('session_date', '>=', today())
            ->with('batch:id,name,meeting_link,meeting_platform')->orderBy('session_date')->orderBy('start_time')->limit(20)->get();

        return view('dashboards.student-timetable', compact('batches', 'sessions'));
    }

    public function enroll(Request $request, User $student)
    {
        abort_unless($student->isStudent(), 404);
        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $batch = Batch::findOrFail($request->batch_id);
            $enrollment = $this->enrollmentService->enrollStudent(
                $student->id,
                $batch->id,
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
        if ($totalSessions === 0) {
            return 0;
        }

        $presentSessions = $student->attendances()->where('status', 'present')->count();

        return round(($presentSessions / $totalSessions) * 100, 1);
    }
}
