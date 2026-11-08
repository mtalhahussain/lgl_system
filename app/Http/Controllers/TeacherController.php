<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Services\SalaryCalculatorService;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        $list = ListQuery::for(User::where('role', 'teacher')->withCount('taughtBatches'), $request)
            ->search(['name', 'email', 'phone', 'employee_id'])
            ->filter('status', fn ($query, $status) => $query->where('is_active', $status === 'active'), ['active', 'inactive'])
            ->sorts(['name' => 'name', 'joined' => 'created_at'], '-joined');
        if ($list->wantsCsv()) {
            return $list->csv('teachers', ['Name' => fn ($user) => $user->name, 'Email' => fn ($user) => $user->email,
                'Phone' => fn ($user) => $user->phone, 'Batches' => fn ($user) => $user->taught_batches_count,
                'Status' => fn ($user) => $user->is_active ? 'Active' : 'Inactive', 'Joined' => fn ($user) => $user->created_at?->toDateString()]);
        }
        $counts = $list->counts(['active' => 'users.is_active = 1',
            'teaching' => "EXISTS (SELECT 1 FROM batches WHERE batches.teacher_id = users.id AND batches.status = 'ongoing')"]);
        $kpis = [['label' => 'Teachers', 'value' => $counts['total'], 'icon' => 'fa-chalkboard-teacher'],
            ['label' => 'Active', 'value' => $counts['active'], 'icon' => 'fa-user-check'],
            ['label' => 'Inactive', 'value' => $counts['total'] - $counts['active'], 'icon' => 'fa-user-slash'],
            ['label' => 'Teaching now', 'value' => $counts['teaching'], 'icon' => 'fa-play', 'note' => 'With an ongoing batch']];
        $filters = [['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']]];

        return view('admin.people.index', ['people' => $list->paginate(), 'kind' => 'teachers', 'kpis' => $kpis, 'filters' => $filters]);
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
            'qualification' => ['nullable', 'string', Rule::in(config('teaching.qualification'))],
            'experience_years' => ['nullable', 'integer', Rule::in(config('teaching.experience_years'))],
            'specialization' => ['nullable', 'string', Rule::in(config('teaching.specialization'))],
            'emergency_contact' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'salary_type' => 'sometimes|in:monthly,per_student',
            'monthly_salary' => 'nullable|decimal:0,2|min:0|max:99999999.99',
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

        $batches = $teacher->taughtBatches()->with('course:id,name,level')->withCount('activeEnrollments')
            ->orderByRaw("CASE status WHEN 'ongoing' THEN 0 WHEN 'upcoming' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderByDesc('start_date')->get();
        $dues = \App\Models\TeacherDue::with(['batch:id,name', 'payouts.reversal'])->where('teacher_id', $teacher->id)
            ->orderByDesc('id')->get();

        return view('admin.people.profile', ['person' => $teacher, 'kind' => 'teachers', 'enrollments' => collect(),
            'batches' => $batches, 'dues' => $dues]);
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
            'qualification' => ['nullable', 'string', Rule::in([...config('teaching.qualification'), $teacher->qualification])],
            'experience_years' => ['nullable', 'integer', Rule::in([...config('teaching.experience_years'), $teacher->experience_years])],
            'specialization' => ['nullable', 'string', Rule::in([...config('teaching.specialization'), $teacher->specialization])],
            'emergency_contact' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'salary_type' => 'sometimes|in:monthly,per_student',
            'monthly_salary' => 'nullable|decimal:0,2|min:0|max:99999999.99',
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data['experience_years'] = $data['experience_years'] ?? 0;
        DB::transaction(fn () => $teacher->fill($data)->save());

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
        $batches = auth()->user()->taughtBatches()->select('id', 'name', 'course_id', 'status', 'start_date', 'end_date')->with(['course:id,name,level', 'schedules'])->withCount('activeEnrollments')->get();
        $todaySessions = \App\Models\ClassSession::whereHas('batch', fn ($query) => $query->where('teacher_id', auth()->id())->where('status', '!=', 'cancelled'))
            ->where('status', '!=', 'cancelled')->whereDate('session_date', today())
            ->with('batch:id,name')->orderBy('start_time')->get();
        $sessions = \App\Models\ClassSession::whereHas('batch', fn ($query) => $query->where('teacher_id', auth()->id()))
            ->where('status', 'scheduled')->whereDate('session_date', '>=', today())->with('batch:id,name')->orderBy('session_date')->orderBy('start_time')->limit(10)->get();

        return view('dashboards.teacher', compact('batches', 'sessions', 'todaySessions'));
    }

    public function calculateSalary(User $teacher, $month = null, $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        return $this->salaryCalculatorService->calculateMonthlySalary($teacher, $month, $year);
    }
}
