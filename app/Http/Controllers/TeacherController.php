<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\TeacherDue;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SalaryCalculatorService;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'phone' => 'required|string|max:20|regex:/^\+?[0-9]+$/D',
            'address' => 'required|string|max:500',
            'date_of_birth' => 'nullable|date|before:today',
            'qualification' => ['nullable', 'string', Rule::in(config('teaching.qualification'))],
            'experience_years' => ['nullable', 'integer', Rule::in(config('teaching.experience_years'))],
            'specialization' => ['nullable', 'string', Rule::in(config('teaching.specialization'))],
            'emergency_contact' => 'nullable|string|max:20|regex:/^\+?[0-9]+$/D',
            'hire_date' => 'nullable|date',
            'salary_type' => 'sometimes|in:monthly,per_student',
            'per_student_amount' => 'required_if:salary_type,per_student|nullable|decimal:0,2|gt:0|max:99999999.99',
            'monthly_salary' => 'nullable|decimal:0,2|min:0|max:99999999.99',
            'password' => 'nullable|required_with:password_confirmation|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
        ]);
        DB::transaction(function () use ($data, $request) {
            $data['role'] = 'teacher';
            $data['password'] = ! empty($data['password']) ? $data['password'] : Str::random(64);
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
        $dues = TeacherDue::with(['batch:id,name', 'payouts.reversal'])->where('teacher_id', $teacher->id)
            ->orderByDesc('id')->get();
        $otherTeachers = auth()->user()->isAdmin()
            ? User::where('role', 'teacher')->where('is_active', true)->where('id', '!=', $teacher->id)->orderBy('name')->pluck('name', 'id')
            : collect();
        $openBatchCount = $batches->whereIn('status', ['upcoming', 'ongoing'])->count();

        return view('admin.people.profile', ['person' => $teacher, 'kind' => 'teachers', 'enrollments' => collect(),
            'batches' => $batches, 'dues' => $dues, 'otherTeachers' => $otherTeachers, 'openBatchCount' => $openBatchCount]);
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
            'phone' => 'required|string|max:20|regex:/^\+?[0-9]+$/D',
            'address' => 'required|string|max:500',
            'date_of_birth' => 'nullable|date|before:today',
            'qualification' => ['nullable', 'string', Rule::in([...config('teaching.qualification'), $teacher->qualification])],
            'experience_years' => ['nullable', 'integer', Rule::in([...config('teaching.experience_years'), $teacher->experience_years])],
            'specialization' => ['nullable', 'string', Rule::in([...config('teaching.specialization'), $teacher->specialization])],
            'emergency_contact' => 'nullable|string|max:20|regex:/^\+?[0-9]+$/D',
            'hire_date' => 'nullable|date',
            'salary_type' => 'sometimes|in:monthly,per_student',
            'per_student_amount' => [Rule::requiredIf($request->input('salary_type', $teacher->salary_type) === 'per_student'), 'nullable', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'monthly_salary' => 'nullable|decimal:0,2|min:0|max:99999999.99',
            'password' => 'nullable|required_with:password_confirmation|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($teacher->is_active && array_key_exists('is_active', $data) && ! $data['is_active'] && $teacher->hasOpenBatches()) {
            throw ValidationException::withMessages(['is_active' => "Reassign this teacher's active and upcoming batches before deactivating them."]);
        }
        $data['experience_years'] = $data['experience_years'] ?? 0;
        DB::transaction(fn () => $teacher->fill($data)->save());

        return redirect()->route('teachers.show', $teacher)->with('success', 'Teacher updated successfully.');
    }

    public function destroy(User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);
        if ($teacher->is_active && $teacher->hasOpenBatches()) {
            return redirect()->route('teachers.show', $teacher)
                ->with('error', "Reassign this teacher's active and upcoming batches before deactivating them.");
        }
        DB::transaction(fn () => $teacher->update(['is_active' => false]));

        return redirect()->route('teachers.index')->with('success', 'Teacher deactivated. Assigned batches and history preserved.');
    }

    /**
     * Teacher-leave offboarding: move all of a leaving teacher's upcoming and ongoing batches
     * to another active teacher in one transaction. Completed/cancelled batches and past payroll
     * dues are left untouched (history). Optionally deactivate the leaving teacher afterwards.
     */
    public function reassignBatches(Request $request, User $teacher)
    {
        abort_unless($teacher->isTeacher(), 404);
        $data = $request->validate([
            'new_teacher_id' => ['required', 'integer', Rule::notIn([$teacher->id]),
                Rule::exists('users', 'id')->where('role', 'teacher')->where('is_active', true)],
            'reason' => 'nullable|string|max:500',
            'deactivate' => 'sometimes|boolean',
        ]);
        $deactivate = $request->boolean('deactivate');

        $moved = DB::transaction(function () use ($data, $teacher, $deactivate) {
            $newTeacher = User::where('id', $data['new_teacher_id'])->where('role', 'teacher')
                ->where('is_active', true)->lockForUpdate()->firstOrFail();
            $leaving = User::lockForUpdate()->findOrFail($teacher->id);
            $batches = Batch::where('teacher_id', $leaving->id)
                ->whereIn('status', ['upcoming', 'ongoing'])->lockForUpdate()->get();
            if ($batches->isEmpty()) {
                throw ValidationException::withMessages(['new_teacher_id' => 'This teacher has no active or upcoming batches to reassign.']);
            }
            foreach ($batches as $batch) {
                $before = $batch->teacher_id;
                $batch->update(['teacher_id' => $newTeacher->id]);
                AuditLog::create(['actor_id' => auth()->id(), 'action' => 'batch.teacher_reassigned',
                    'subject_type' => 'batch', 'subject_id' => $batch->id,
                    'changes' => ['before' => ['teacher_id' => $before], 'after' => ['teacher_id' => $newTeacher->id],
                        'from_teacher' => $leaving->name, 'to_teacher' => $newTeacher->name, 'reason' => $data['reason'] ?? null]]);
            }
            if ($deactivate) {
                $leaving->update(['is_active' => false]);
            }
            app(NotificationService::class)->toUser($newTeacher->id, [
                'type' => 'batch', 'icon' => 'fa-people-arrows', 'level' => 'info',
                'title' => 'Batches assigned to you',
                'body' => $batches->count().' '.Str::plural('batch', $batches->count())." moved to you from {$leaving->name}.",
                'url' => route('batches.index'),
            ]);

            return $batches->count();
        }, 3);

        $message = $moved.' '.Str::plural('batch', $moved).' reassigned'
            .($deactivate ? ' and the teacher was deactivated.' : '.');

        return redirect()->route('teachers.show', $teacher)->with('success', $message);
    }

    public function dashboard()
    {
        $batches = auth()->user()->taughtBatches()->select('id', 'name', 'course_id', 'status', 'start_date', 'end_date')->with(['course:id,name,level', 'schedules'])->withCount('activeEnrollments')->get();
        $todaySessions = ClassSession::whereHas('batch', fn ($query) => $query->where('teacher_id', auth()->id())->where('status', '!=', 'cancelled'))
            ->where('status', '!=', 'cancelled')->whereDate('session_date', today())
            ->with('batch:id,name')->orderBy('start_time')->get();
        $sessions = ClassSession::whereHas('batch', fn ($query) => $query->where('teacher_id', auth()->id()))
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
