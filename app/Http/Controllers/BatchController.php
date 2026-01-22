<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BatchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin,teacher')->only(['index', 'show']);
        $this->middleware('role:admin')->except(['apiIndex', 'apiShow', 'index', 'show']);
    }

    public function index(Request $request)
    {
        $query = Batch::with(['course', 'teacher', 'enrollments']);
        
        // Filter by teacher for teacher role
        if (auth()->user()->role === 'teacher') {
            $query->where('teacher_id', auth()->id());
        }
        
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $batches = $query->orderBy('start_date', 'desc')->paginate(15);
        $courses = Course::all();
        $teachers = User::where('role', 'teacher')->get();
        
        // Adjust stats for teacher view
        if (auth()->user()->role === 'teacher') {
            $stats = [
                'total' => Batch::where('teacher_id', auth()->id())->count(),
                'active' => Batch::where('teacher_id', auth()->id())->where('status', 'ongoing')->count(),
                'completed' => Batch::where('teacher_id', auth()->id())->where('status', 'completed')->count(),
                'upcoming' => Batch::where('teacher_id', auth()->id())->where('status', 'upcoming')->count()
            ];
        } else {
            $stats = [
                'total' => Batch::count(),
                'active' => Batch::where('status', 'ongoing')->count(),
                'completed' => Batch::where('status', 'completed')->count(),
                'upcoming' => Batch::where('status', 'upcoming')->count()
            ];
        }

        return view('admin.batches.index', compact('batches', 'courses', 'teachers', 'stats'));
    }

    public function create()
    {
        $courses = Course::all();
        $teachers = User::where('role', 'teacher')->get();
        return view('admin.batches.create', compact('courses', 'teachers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'course_id' => 'required|exists:courses,id',
            'teacher_id' => 'required|exists:users,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'class_time' => 'required|string',
            'max_students' => 'required|integer|min:1|max:50',
            'days_of_week' => 'required|array',
            'days_of_week.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'
        ]);

        $batch = Batch::create([
            'name' => $request->name,
            'course_id' => $request->course_id,
            'teacher_id' => $request->teacher_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'class_time' => $request->class_time,
            'max_students' => $request->max_students,
            'days_of_week' => $request->days_of_week,
            'status' => Carbon::parse($request->start_date)->isFuture() ? 'upcoming' : 'ongoing'
        ]);

        // Snapshot teacher's salary info for this batch
        $teacher = User::find($request->teacher_id);
        $salaryType = $teacher->salary_type;
        if ($salaryType === 'monthly') {
            $salaryAmount = $teacher->monthly_salary;
        } elseif ($salaryType === 'per_batch') {
            $salaryAmount = $teacher->per_batch_amount;
        } elseif ($salaryType === 'per_student') {
            $salaryAmount = $teacher->per_student_amount;
        } else {
            $salaryAmount = 0;
        }
        \App\Models\BatchTeacherEarning::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'salary_type' => $salaryType,
            'salary_amount' => $salaryAmount,
        ]);

        return redirect()->route('batches.index')
            ->with('success', 'Batch created successfully!');
    }

    public function show(Batch $batch)
    {
        // Check if teacher can view this batch (only their assigned batches)
        if (auth()->user()->role === 'teacher') {
            if ($batch->teacher_id !== auth()->id()) {
                abort(403, 'You can only view your assigned batches.');
            }
        }

        $batch->load(['course', 'teacher', 'enrollments.student', 'enrollments.feeInstallments', 'classSessions']);
        
        $stats = [
            'enrolled_students' => $batch->enrollments()->where('status', 'active')->count(),
            'available_spots' => $batch->max_students - $batch->enrollments()->where('status', 'active')->count(),
            'total_sessions' => $batch->classSessions()->count(),
            'completed_sessions' => $batch->classSessions()->where('status', 'completed')->count(),
            'total_fees_collected' => $batch->feeInstallments()->where('fee_installments.status', 'paid')->sum('amount'),
            'pending_fees' => $batch->feeInstallments()->where('fee_installments.status', 'pending')->sum('amount')
        ];

        return view('admin.batches.show', compact('batch', 'stats'));
    }

    public function edit(Batch $batch)
    {
        $courses = Course::all();
        $teachers = User::where('role', 'teacher')->get();
        return view('admin.batches.edit', compact('batch', 'courses', 'teachers'));
    }

    public function update(Request $request, Batch $batch)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'course_id' => 'required|exists:courses,id',
            'teacher_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'class_time' => 'required|string',
            'max_students' => 'required|integer|min:1|max:50',
            'days_of_week' => 'required|array',
            'days_of_week.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'status' => 'required|in:scheduled,active,completed,cancelled'
        ]);

        $batch->update($request->all());

        return redirect()->route('batches.show', $batch)
            ->with('success', 'Batch updated successfully!');
    }

    public function destroy(Batch $batch)
    {
        if ($batch->enrollments()->where('status', 'active')->exists()) {
            return back()->withErrors(['error' => 'Cannot delete batch with active enrollments.']);
        }

        $batch->delete();
        return redirect()->route('batches.index')
            ->with('success', 'Batch deleted successfully!');
    }

    // API methods for backward compatibility  
    public function apiIndex(Request $request)
    {
        $this->authorize('viewAny', Batch::class);
        
        $query = Batch::with(['course', 'teacher']);
        
        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $batches = $query->paginate(10);
        
        return response()->json($batches);
    }

    public function apiShow(Batch $batch)
    {
        $this->authorize('view', $batch);
        $batch->load(['course', 'teacher', 'activeEnrollments', 'classSessions']);
        return response()->json($batch);
    }

    public function enrollments(Batch $batch)
    {
        $this->authorize('view', $batch);
        
        $enrollments = $batch->enrollments()
            ->with(['student', 'feeInstallments'])
            ->paginate(15);
        
        return response()->json($enrollments);
    }

    public function sessions(Batch $batch)
    {
        $this->authorize('view', $batch);
        
        $sessions = $batch->classeSessions()
            ->with(['attendances.student'])
            ->orderBy('session_date', 'desc')
            ->paginate(15);
        
        return response()->json($sessions);
    }

    public function earnings(Batch $batch)
    {
        $this->authorize('viewFinancials', $batch);
        
        $earnings = $batch->teacherEarnings()
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(12);
        
        return response()->json($earnings);
    }
}