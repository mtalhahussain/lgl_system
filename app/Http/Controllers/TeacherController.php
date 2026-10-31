<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Services\SalaryCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherController extends Controller
{
    protected $salaryCalculatorService;

    public function __construct(SalaryCalculatorService $salaryCalculatorService)
    {
        $this->middleware('auth');
        $this->middleware('role:admin,teacher')->except(['dashboard', 'show']);
        $this->salaryCalculatorService = $salaryCalculatorService;
    }

    public function index(Request $request)
    {
        $query = User::where('role', 'teacher')
            ->with('taughtBatches.course')
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('employee_id', 'LIKE', "%{$search}%");
            });
        }

        $teachers = $query->paginate(15);

        return view('admin.people.index', ['people' => $teachers, 'kind' => 'teachers']);
    }

    public function create()
    {
        $courses = Course::all();

        return view('admin.people.form', ['kind' => 'teachers', 'person' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'date_of_birth' => 'nullable|date|before:today',
            'qualification' => 'nullable|string|max:255',
            'experience_years' => 'nullable|integer|min:0',
            'specialization' => 'nullable|string|max:255',
            'emergency_contact' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'password' => 'required|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
        ]);
        DB::transaction(function () use ($data, $request) {
            $data['role'] = 'teacher';
            $data['is_active'] = $request->boolean('is_active', true);
            $data['experience_years'] = $data['experience_years'] ?? 0;
            $teacher = User::create($data);
            $teacher->update(['employee_id' => 'TCH'.str_pad((string) $teacher->id, 4, '0', STR_PAD_LEFT)]);
        });

        return redirect()->route('teachers.index')->with('success', 'Teacher created successfully.');
    }

    public function show(User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);
        if (auth()->user()->role === 'teacher' && auth()->id() !== $teacher->id) {
            abort(403);
        }

        $teacher->load('taughtBatches.course');

        return view('admin.people.profile', ['person' => $teacher, 'kind' => 'teachers', 'enrollments' => collect(), 'batches' => $teacher->taughtBatches]);
    }

    public function edit(User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);
        $courses = Course::all();

        return view('admin.people.form', ['kind' => 'teachers', 'person' => $teacher]);
    }

    public function update(Request $request, User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$teacher->id,
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'date_of_birth' => 'nullable|date|before:today',
            'qualification' => 'nullable|string|max:255',
            'experience_years' => 'nullable|integer|min:0',
            'specialization' => 'nullable|string|max:255',
            'emergency_contact' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data['experience_years'] = $data['experience_years'] ?? 0;
        DB::transaction(fn () => $teacher->update($data));

        return redirect()->route('teachers.show', $teacher)->with('success', 'Teacher updated successfully.');
    }

    public function destroy(User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);
        DB::transaction(fn () => $teacher->update(['is_active' => false]));

        return redirect()->route('teachers.index')->with('success', 'Teacher deactivated. Assigned batches and history preserved.');
    }

    public function dashboard()
    {
        $batches = auth()->user()->taughtBatches()->select('id', 'name', 'course_id', 'status')->with('course:id,name,level')->withCount('activeEnrollments')->get();
        $sessions = \App\Models\ClassSession::whereHas('batch', fn ($query) => $query->where('teacher_id', auth()->id()))
            ->where('status', 'scheduled')->whereDate('session_date', '>=', today())->with('batch:id,name')->orderBy('session_date')->orderBy('start_time')->limit(10)->get();

        return view('dashboards.teacher', compact('batches', 'sessions'));
    }

    public function calculateSalary(User $teacher, $month = null, $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        return $this->salaryCalculatorService->calculateMonthlySalary($teacher, $month, $year);
    }
}
