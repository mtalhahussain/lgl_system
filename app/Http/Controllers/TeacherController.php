<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Batch;
use App\Models\TeacherEarning;
use App\Services\SalaryCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            ->with(['taughtBatches.course', 'teacherEarnings'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('employee_id', 'LIKE', "%{$search}%");
            });
        }

        $teachers = $query->paginate(15);
        
        $stats = [
            'total' => User::where('role', 'teacher')->count(),
            'active' => User::where('role', 'teacher')
                ->whereHas('taughtBatches', function($q) {
                    $q->where('status', 'active');
                })->count(),
            'total_earnings_month' => TeacherEarning::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total_earning'),
            'average_salary' => TeacherEarning::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->avg('total_earning')
        ];

        return view('admin.teachers.index', compact('teachers', 'stats'));
    }

    public function create()
    {
        $courses = Course::all();
        return view('admin.teachers.create', compact('courses'));
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'date_of_birth' => 'required|date',
            'qualification' => 'nullable|string|max:255',
            'experience_years' => 'required|integer|min:0',
            'specialization' => 'nullable|string|max:255',
            'emergency_contact' => 'nullable|string|max:20',
            'hire_date' => 'required|date',
            'password' => 'required|string|min:6',
            'bio' => 'nullable|string',
            'salary_type' => 'required|in:monthly,per_batch,per_student',
        ];

        // Add conditional salary validation based on salary type
        if ($request->salary_type === 'monthly') {
            $rules['monthly_salary'] = 'required|numeric|min:0';
        } elseif ($request->salary_type === 'per_batch') {
            $rules['per_batch_amount'] = 'required|numeric|min:0';
        } elseif ($request->salary_type === 'per_student') {
            $rules['per_student_amount'] = 'required|numeric|min:0';
        }

        $request->validate($rules);

        DB::beginTransaction();
        try {
            $teacher = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'teacher',
                'phone' => $request->phone,
                'address' => $request->address,
                'date_of_birth' => $request->date_of_birth,
                'qualification' => $request->qualification,
                'experience_years' => $request->experience_years,
                'specialization' => $request->specialization,
                'emergency_contact' => $request->emergency_contact,
                'hire_date' => $request->hire_date,
                'bio' => $request->bio,
                'is_active' => $request->has('is_active'),
                'salary_type' => $request->salary_type,
                'monthly_salary' => $request->salary_type === 'monthly' ? $request->monthly_salary : null,
                'per_batch_amount' => $request->salary_type === 'per_batch' ? $request->per_batch_amount : null,
                'per_student_amount' => $request->salary_type === 'per_student' ? $request->per_student_amount : null,
                'employee_id' => 'TCH' . str_pad(User::where('role', 'teacher')->count() + 1, 4, '0', STR_PAD_LEFT)
            ]);

            DB::commit();
            
            return redirect()->route('teachers.index')
                ->with('success', 'Teacher created successfully with salary configuration!');
                
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->withErrors(['error' => 'Failed to create teacher: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function show(User $teacher)
    {
        if (auth()->user()->role === 'teacher' && auth()->id() !== $teacher->id) {
            abort(403);
        }

        $teacher->load([
            'taughtBatches.course',
            'taughtBatches.enrollments.student',
            'teacherEarnings'
        ]);

        $stats = [
            'active_batches' => $teacher->taughtBatches()->where('status', 'active')->count(),
            'total_students' => $teacher->taughtBatches()
                ->withCount(['enrollments as active_students_count' => function($q) {
                    $q->where('status', 'active');
                }])
                ->get()
                ->sum('active_students_count'),
            'monthly_earnings' => $teacher->teacherEarnings()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total_earning'),
            'total_earnings' => $teacher->teacherEarnings()->sum('total_earning')
        ];

        $recentEarnings = $teacher->teacherEarnings()
            ->with('batch.course')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return view('admin.teachers.show', compact('teacher', 'stats', 'recentEarnings'));
    }

    public function edit(User $teacher)
    {
        $courses = Course::all();
        return view('admin.teachers.edit', compact('teacher', 'courses'));
    }

    public function update(Request $request, User $teacher)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $teacher->id,
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'date_of_birth' => 'required|date',
            'qualification' => 'required|string|max:255',
            'experience_years' => 'required|integer|min:0',
            'specialization' => 'required|string|max:255',
            'hourly_rate' => 'required|numeric|min:0',
            'per_student_rate' => 'required|numeric|min:0'
        ]);

        $teacher->update($request->only([
            'name', 'email', 'phone', 'address', 'date_of_birth',
            'qualification', 'experience_years', 'specialization',
            'hourly_rate', 'per_student_rate'
        ]));

        return redirect()->route('teachers.show', $teacher)
            ->with('success', 'Teacher updated successfully!');
    }

    public function destroy(User $teacher)
    {
        if ($teacher->taughtBatches()->where('status', 'ongoing')->exists()) {
            return back()->withErrors(['error' => 'Cannot delete teacher with active batches.']);
        }

        $teacher->delete();
        return redirect()->route('teachers.index')
            ->with('success', 'Teacher deleted successfully!');
    }

    public function dashboard()
    {
        $teacher = auth()->user();
        $teacher->load([
            'taughtBatches.course',
            'taughtBatches.enrollments.student',
            'teacherEarnings'
        ]);

        $stats = [
            'active_batches' => $teacher->taughtBatches()->where('status', 'active')->count(),
            'total_students' => $teacher->taughtBatches()
                ->withCount(['enrollments as active_students_count' => function($q) {
                    $q->where('status', 'active');
                }])
                ->get()
                ->sum('active_students_count'),
            'monthly_earnings' => $teacher->teacherEarnings()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total_earning'),
            'pending_sessions' => $teacher->taughtBatches()
                ->where('status', 'active')
                ->whereHas('classSessions', function($q) {
                    $q->whereDate('session_date', '>=', now())
                      ->where('status', 'scheduled');
                })
                ->count()
        ];

        $upcomingSessions = $teacher->taughtBatches()
            ->with('course')
            ->where('status', 'active')
            ->whereHas('classSessions', function($q) {
                $q->whereDate('session_date', '>=', now())
                  ->where('status', 'scheduled');
            })
            ->take(5)
            ->get();

        $recentEarnings = $teacher->teacherEarnings()
            ->with('batch.course')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('teacher.dashboard', compact('teacher', 'stats', 'upcomingSessions', 'recentEarnings'));
    }

    public function calculateSalary(User $teacher, $month = null, $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        
        return $this->salaryCalculatorService->calculateMonthlySalary($teacher, $month, $year);
    }
}